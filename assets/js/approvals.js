function approveDocument(documentName){


Swal.fire({

title:"Approve Document?",

text:documentName,

icon:"question",

showCancelButton:true,

confirmButtonColor:"#198754",

confirmButtonText:"Approve"


}).then((result)=>{


if(result.isConfirmed){


Swal.fire({

icon:"success",

title:"Approved",

text:documentName+" has been approved."

});


}


});


}




function rejectDocument(documentName){


Swal.fire({

title:"Reject Document?",

input:"textarea",

inputLabel:"Reason for rejection",

inputPlaceholder:"Enter comments...",


showCancelButton:true,


confirmButtonColor:"#dc3545",


confirmButtonText:"Reject"


}).then((result)=>{


if(result.isConfirmed){


Swal.fire({

icon:"success",

title:"Rejected",

text:documentName+" has been rejected."

});


}


});


}