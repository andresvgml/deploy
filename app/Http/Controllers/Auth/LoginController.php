<?php

namespace App\Http\Controllers\Auth;

use App\User;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;

use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

use Socialite;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Redirect the user to the GitHub authentication page.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider(Request $request)
    {
        $request->validate([
            'provider' => 'in:github,slack'
        ]);

        return Socialite::with($request->provider)->redirect();
    }

    /**
     * Obtain the user information from GitHub.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback()
    {
        try {
            $user = Socialite::driver('github')->user();
        } catch (\Exception $e) {
            return Redirect::to('auth/github');
        }

        try {
            \Log::debug("Usuario Github", [$user]);

            $authUser = $this->findOrCreateUserGithub($user);

            Auth::login($authUser, true);

            return Redirect::to('home');
        } catch (\Exception $ex) {
            \Log::error('Ha ocurrido un error la realizar el login', [$ex->getMessage()]);
            Auth::logout();
            return Redirect::to('login');
        }
    }

    /**
     * Obtain the user information from GitHub.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleSlackProviderCallback()
    {
        try {
            $user = Socialite::driver('slack')->user();
            \Log::debug("Usuario Slack", [$user]);

            $authUser = $this->findOrCreateUserSlack($user->accessTokenResponseBody);

            Auth::login($authUser, true);

            return Redirect::to('home');
        } catch (\Exception $ex) {
            \Log::error('Ha ocurrido un error la realizar el login', [$ex->getMessage()]);
            Auth::logout();
            return Redirect::to('login');
        }
    }

    /**
     * Deletes all session data
     *
     * @return void
     */
    public function logout(){
		Auth::logout();
		Session::flush();
		return Redirect::to('login');
	}

    /**
     * Return user if exists; create and return if doesn't
     *
     * @param $githubUser
     * @return User
     */
    private function findOrCreateUserGithub($githubUser)
    {
        // Verifica que el usuario tenga acceso al repositorio
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', 
            config('services.github.api_project') . $githubUser->getNickname(), [
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.github.access_token')
            ]
        ]);

        if($response->getStatusCode() != "204"){
            User::where('github_id', $githubUser->id)->delete();
            throw new \Exception("El usuario no posee los permisos correctos", 404);
        }

        if ($authUser = User::where('github_id', $githubUser->id)->first()) {
            return $authUser;
        }

        return User::create([
            'name'      => ($githubUser->name) ? $githubUser->name : $githubUser->email,
            'email'     => $githubUser->email,
            'github_id' => $githubUser->id,
            'avatar'    => $githubUser->avatar
        ]);
    }

    /**
     * Return user if exists; create and return if doesn't
     *
     * @param $githubUser
     * @return User
     */
    private function findOrCreateUserSlack($slackUser)
    {
        // Verifica que el usuario tenga acceso al equipo
        if(
            !isset($slackUser['team_id']) || 
            $slackUser['team_id'] != config('services.slack.team_id') || 
            !$slackUser['ok']
        ){
            throw new \Exception("El usuario no posee los permisos correctos", 404);
        }

        // Valida si el usuario existe
        if ($authUser = User::where('github_id', $slackUser['user_id'])->first()) {
            return $authUser;
        }

        return User::create([
            'name'      => ($slackUser['user']['name']) ? $slackUser['user']['name'] : $slackUser['user']['email'],
            'email'     => $slackUser['user']['email'],
            'github_id' => $slackUser['user_id'],
            'avatar'    => $slackUser['user']['image_32']
        ]);
    }
}