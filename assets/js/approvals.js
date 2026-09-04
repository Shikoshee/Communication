console.log("Approvals JS loaded");


function approveDocument(documentId){

    Swal.fire({

        title:"Approve Document?",

        text:"Are you sure you want to approve this document? This action cannot be undone.",

        icon:"question",

        showCancelButton:true,

        confirmButtonColor:"#198754",

        confirmButtonText:"Approve"

    }).then((result)=>{

        if(result.isConfirmed){

            fetch("api/documents/approve.php",{

                method:"POST",

                headers:{
                    "Content-Type":"application/x-www-form-urlencoded"
                },

                body:
                "id="+documentId

            })

            .then(response=>response.json())

            .then(data=>{

                if(data.success){

                    Swal.fire({

                        icon:"success",

                        title:"Approved",

                        text:data.message

                    })
                    .then(()=>{

                        location.reload();

                    });

                }else{

                    Swal.fire({

icon:"error",

title:"Failed",

text:data.message

});

                }

            });

        }

    });

}



function rejectDocument(id){

    Swal.fire({

        title:"Reject Document?",

        input:"textarea",

        inputLabel:"Reason",

        inputPlaceholder:"Enter rejection reason...",


        showCancelButton:true,

        confirmButtonText:"Reject",

        confirmButtonColor:"#dc3545",

        cancelButtonText:"Cancel"


    }).then((result)=>{


        if(result.isConfirmed){


            fetch("api/documents/reject.php",{

                method:"POST",

                headers:{
                    "Content-Type":"application/x-www-form-urlencoded"
                },

                body:
                "id="+id+
                "&reason="+encodeURIComponent(result.value)


            })

            .then(response=>response.json())

            .then(data=>{


                console.log(data);


                if(data.success){


                    Swal.fire({

                        icon:"success",

                        title:"Rejected",

                        text:data.message

                    })
                    .then(()=>{

                        location.reload();

                    });


                }
                else{


                    Swal.fire({

                        icon:"error",

                        title:"Failed",

                        text:data.message

                    });


                }


            })

            .catch(error=>{


                console.error(error);


                Swal.fire({

                    icon:"error",

                    title:"Server Error",

                    text:"Could not process rejection."

                });


            });


        }


    });


}


// MUST BE OUTSIDE THE OTHER FUNCTIONS

function reviewDocument(id){

    console.log("Reviewing document:", id);

    window.location.href =
    "review-document.php?id="+id;

}