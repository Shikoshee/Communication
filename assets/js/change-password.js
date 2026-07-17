console.log("Change password loaded");


document.getElementById("changePasswordForm")
.addEventListener("submit",function(e){


e.preventDefault();



let newPassword =
document.getElementById("newPassword").value;


let confirmPassword =
document.getElementById("confirmPassword").value;



if(newPassword !== confirmPassword){


Swal.fire(

"Error",

"Passwords do not match",

"error"

);

return;

}



fetch("api/users/change-password.php",{

method:"POST",

headers:{

"Content-Type":
"application/x-www-form-urlencoded"

},


body:new URLSearchParams({

current_password:
document.getElementById("currentPassword").value,


new_password:newPassword


})


})


.then(r=>r.json())


.then(data=>{


if(data.success){


Swal.fire({

icon:"success",

title:"Success",

text:data.message

})
.then(()=>{


window.location="dashboard.php";


});


}else{


Swal.fire(

"Error",

data.message,

"error"

);


}


});


});