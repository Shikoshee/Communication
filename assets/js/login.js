const togglePassword = document.getElementById("togglePassword");

const password = document.getElementById("password");

togglePassword.addEventListener("click", () => {

    if(password.type==="password"){

        password.type="text";

        togglePassword.classList.replace("fa-eye","fa-eye-slash");

    }else{

        password.type="password";

        togglePassword.classList.replace("fa-eye-slash","fa-eye");

    }

});

document.getElementById("loginForm").addEventListener("submit",function(e){

    e.preventDefault();

    Swal.fire({

        icon:"success",

        title:"Frontend Ready",

        text:"Backend authentication will be added later."

    });

});