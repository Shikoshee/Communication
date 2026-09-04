console.log("Upload JS Loaded");


// ======================================
// FILE NAME DISPLAY
// ======================================

const fileInput = document.getElementById("documentFile");
const fileName = document.getElementById("fileName");


if(fileInput){

    fileInput.addEventListener("change",()=>{

        if(fileInput.files.length){

            fileName.innerHTML =
            fileInput.files[0].name;

        }

    });

}





// ======================================
// UPLOAD DOCUMENT
// ======================================


const uploadForm = document.getElementById("uploadForm");


if(uploadForm){


uploadForm.addEventListener("submit",function(e){


e.preventDefault();



const formData = new FormData(this);




const progressBar =
document.getElementById("progressBar");



const button =
document.querySelector(".upload-submit");



button.disabled = true;

button.innerHTML = `
    <i class="fa fa-spinner fa-spin"></i>
    Uploading...
`;

Swal.fire({
    title: "Uploading...",
    text: "Please wait while the document is uploaded and shared.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
        Swal.showLoading();
    }
});






const xhr = new XMLHttpRequest();



xhr.open(
"POST",
"api/documents/upload.php",
true
);





// ======================================
// UPLOAD PROGRESS
// ======================================


xhr.upload.addEventListener(
"progress",
function(e){


if(e.lengthComputable){


let percent =
Math.round(
(e.loaded / e.total) * 100
);


progressBar.style.width =
percent+"%";


progressBar.innerHTML =
percent+"%";


}


});







xhr.onload=function(){



button.disabled=false;


button.innerHTML=
`
<i class="fa fa-upload"></i>
Upload Document
`;




try{


const response =
JSON.parse(xhr.responseText);



if(response.success){

    Swal.fire({
        icon: "success",
        title: "Uploaded",
        text: response.message,
        confirmButtonText: "OK"
    }).then(() => {

        window.location.href = "documents.php";

    });

}
else{

    Swal.fire({
        icon: "error",
        title: "Upload Failed",
        text: response.message
    });

}





}catch(error){



console.log(xhr.responseText);


Swal.fire(

"Error",

"Server returned invalid response",

"error"

);



}




};








xhr.onerror=function(){



button.disabled=false;



Swal.fire(

"Error",

"Upload connection failed",

"error"

);


};





xhr.send(formData);




});


}