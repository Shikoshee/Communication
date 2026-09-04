document
.getElementById("signedForm")
.addEventListener("submit",function(e){


e.preventDefault();


let formData=new FormData(this);



fetch(
"api/documents/upload_signed.php",
{

method:"POST",

body:formData

}

)

.then(r=>r.json())

.then(data=>{


Swal.fire({

icon:data.success?"success":"error",

title:data.message

});


});


});