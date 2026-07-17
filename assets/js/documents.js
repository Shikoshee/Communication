console.log("Documents JS Loaded");


// ======================================
// SEARCH + FILTER
// ======================================


const documentSearch = document.getElementById("documentSearch");
const departmentFilter = document.getElementById("departmentFilter");
const statusFilter = document.getElementById("statusFilter");


function filterDocuments(){


    const searchValue = documentSearch.value.toLowerCase();

    const departmentValue = departmentFilter.value.toLowerCase();

    const statusValue = statusFilter.value.toLowerCase();



    document.querySelectorAll("#documentsTable tr")
    .forEach(row=>{


        const title =
        row.dataset.title || "";


        const department =
        row.dataset.department || "";


        const status =
        row.dataset.status || "";



        const matchSearch =
        title.includes(searchValue);



        const matchDepartment =
        departmentValue === "" ||
        department === departmentValue;



        const matchStatus =
        statusValue === "" ||
        status === statusValue;



        row.style.display =
        (
            matchSearch &&
            matchDepartment &&
            matchStatus
        )
        ?
        ""
        :
        "none";



    });


}



if(documentSearch){

documentSearch.addEventListener(
"keyup",
filterDocuments
);

}



if(departmentFilter){

departmentFilter.addEventListener(
"change",
filterDocuments
);

}



if(statusFilter){

statusFilter.addEventListener(
"change",
filterDocuments
);

}





// ======================================
// VIEW DOCUMENT
// ======================================


function viewDocument(id){


fetch(
"api/documents/details.php?id="+id
)


.then(response=>response.json())


.then(data=>{


if(!data.success){

Swal.fire(
"Error",
data.message,
"error"
);

return;

}



const d=data.document;



Swal.fire({


title:d.title,


width:700,


html:`


<p>
<strong>Department:</strong>
${d.department_name}
</p>


<p>
<strong>Owner:</strong>
${d.owner_name}
</p>


<p>
<strong>Status:</strong>
${d.status}
</p>


<p>
<strong>Version:</strong>
${d.version}
</p>


<hr>


<p>
${d.description ?? ''}
</p>


`,


confirmButtonText:"Close"


});



});


}







// ======================================
// DELETE DOCUMENT
// ======================================


function deleteDocument(id,title){


Swal.fire({

title:"Delete Document?",

text:"Delete "+title+" permanently?",

icon:"warning",

showCancelButton:true,

confirmButtonColor:"#dc3545",

confirmButtonText:"Delete"


}).then(result=>{


if(!result.isConfirmed)
return;



fetch(
"api/documents/delete.php",
{

method:"POST",

headers:{

"Content-Type":
"application/x-www-form-urlencoded"

},

body:new URLSearchParams({

id:id

})

}

)

.then(response=>response.json())

.then(data=>{


if(data.success){


Swal.fire({

icon:"success",

title:"Deleted",

text:data.message

})
.then(()=>location.reload());



}else{


Swal.fire(

"Error",

data.message,

"error"

);


}


})

.catch(error=>{


console.log(error);


Swal.fire(

"Error",

"Delete request failed",

"error"

);


});


});



}




// ======================================
// SHARE DOCUMENT
// ======================================


function shareDocument(id){
     console.log("Share clicked", id);

    alert("Share button works");

Swal.fire({


title:"Share Document",


html:`


<input

id="shareUser"

class="swal2-input"

placeholder="User ID">


<select

id="permission"

class="swal2-select">


<option value="read">

Read Only

</option>


<option value="edit">

Can Edit

</option>


</select>



`,


showCancelButton:true,


confirmButtonText:"Share",



preConfirm:()=>{


return{


user_id:
document.getElementById("shareUser").value,


permission:
document.getElementById("permission").value


};


}


})
.then(result=>{


if(!result.isConfirmed)
return;



fetch(
"api/documents/share.php",
{


method:"POST",


headers:{


"Content-Type":
"application/x-www-form-urlencoded"


},


body:new URLSearchParams({

document_id:id,

user_id:result.value.user_id,

permission:result.value.permission

})


})


.then(r=>r.json())


.then(data=>{


if(data.success){


Swal.fire(
"Shared",
data.message,
"success"
);


}else{


Swal.fire(
"Error",
data.message,
"error"
);


}


});


});



}







// ======================================
// CHART
// ======================================


const chart =
document.getElementById("documentChart");



if(chart){


new Chart(chart,{


type:"bar",



data:{


labels:documentLabels,


datasets:[{


label:"Documents",


data:documentValues,


backgroundColor:[


"#0B5ED7",

"#198754",

"#F59E0B",

"#DC3545",

"#6F42C1",

"#20C997"


]


}]



},



options:{


responsive:true,


plugins:{


legend:{


display:false


}


}



}



});



}
function approveDocument(id){


updateDocumentStatus(
id,
"approve"
);


}



function rejectDocument(id){


updateDocumentStatus(
id,
"reject"
);


}




function updateDocumentStatus(id,action){


Swal.fire({

title:
action=="approve"
?
"Approve Document?"
:
"Reject Document?",


icon:
action=="approve"
?
"question"
:
"warning",


showCancelButton:true,


confirmButtonText:
"Continue"


})
.then(result=>{


if(!result.isConfirmed)
return;



fetch(
"api/documents/"+action+".php",
{

method:"POST",

headers:{

"Content-Type":
"application/x-www-form-urlencoded"

},

body:new URLSearchParams({

id:id

})

}

)

.then(r=>r.json())

.then(data=>{


if(data.success){


Swal.fire(

"Success",

data.message,

"success"

)
.then(()=>location.reload());


}else{


Swal.fire(

"Error",

data.message,

"error"

);


}


});


}
);}

