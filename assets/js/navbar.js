document
.getElementById("notificationBell")
.addEventListener("click",()=>{


let box=document.getElementById(
"notificationDropdown"
);


box.style.display =
box.style.display==="block"
?
"none"
:
"block";


});





document
.getElementById("profileBtn")
.addEventListener("click",()=>{


let menu=document.getElementById(
"profileMenu"
);


menu.style.display =
menu.style.display==="block"
?
"none"
:
"block";


});





document.addEventListener(
"click",
function(e){


if(
!e.target.closest(".profile-wrapper")
){

document.getElementById(
"profileMenu"
).style.display="none";


}



if(
!e.target.closest(".notification-wrapper")
){

document.getElementById(
"notificationDropdown"
).style.display="none";


}


});