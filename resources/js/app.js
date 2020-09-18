require('./bootstrap');

document.addEventListener("DOMContentLoaded", function () {
    var classname = document.getElementsByClassName("login-form-btn");
    for (var i = 0; i < classname.length; i++) {
        classname[i].addEventListener("click", function () {
            document.getElementById("overlay").style.display = 'block';
        });
    }
});