console.log("Departments JS loaded - NEW VERSION");
function addDepartment(){

Swal.fire({

title:"Add Department",

html:`

<input
id="departmentName"
class="swal2-input"
placeholder="Department Name">

<textarea
id="departmentDescription"
class="swal2-textarea"
placeholder="Description"></textarea>

<select
id="departmentStatus"
class="swal2-select">

<option value="Active">

Active

</option>

<option value="Inactive">

Inactive

</option>

</select>

`,

focusConfirm:false,

showCancelButton:true,

confirmButtonText:"Save",

preConfirm:()=>{

return{

name:document.getElementById("departmentName").value,

description:document.getElementById("departmentDescription").value,

status:document.getElementById("departmentStatus").value

};

}

}).then((result)=>{

if(result.isConfirmed){

saveDepartment(result.value);

}

});

}

function editDepartment(id){

fetch("api/departments/get.php?id="+id)

.then(response=>response.json())

.then(result=>{

if(!result.success){

Swal.fire("Error",result.message,"error");

return;

}

const d=result.department;

Swal.fire({

title:"Edit Department",

html:`

<input id="deptName"

class="swal2-input"

placeholder="Department Name"

value="${d.name}">

<textarea

id="deptDescription"

class="swal2-textarea"

placeholder="Description">${d.description ?? ''}</textarea>

<select

id="deptStatus"

class="swal2-select">

<option value="active">Active</option>

<option value="inactive">Inactive</option>

</select>

`,

didOpen:()=>{

document.getElementById("deptStatus").value=d.status;

},

showCancelButton:true,

confirmButtonText:"Save Changes",

preConfirm:()=>{

return{

id:id,

name:document.getElementById("deptName").value,

description:document.getElementById("deptDescription").value,

status:document.getElementById("deptStatus").value

};

}

}).then((form)=>{

if(!form.isConfirmed) return;

fetch("api/departments/update.php",{

method:"POST",

headers:{

"Content-Type":"application/x-www-form-urlencoded"

},

body:new URLSearchParams(form.value)

})

.then(r=>r.json())

.then(data=>{

if(data.success){

Swal.fire({

icon:"success",

title:"Updated",

text:data.message

}).then(()=>location.reload());

}else{

Swal.fire("Error",data.message,"error");

}

});

});

});

}

function deleteDepartment(id,name){

    Swal.fire({

        title:"Delete Department?",

        text:"Delete "+name+"?",

        icon:"warning",

        showCancelButton:true,

        confirmButtonColor:"#DC3545",

        confirmButtonText:"Delete"

    }).then((result)=>{

    if(!result.isConfirmed) return;

    fetch("api/departments/delete.php",{

        method:"POST",

        headers:{
            "Content-Type":"application/x-www-form-urlencoded"
        },

        body:new URLSearchParams({
            id:id
        })

    })

    .then(r=>r.json())

    .then(data=>{

        if(data.success){

            Swal.fire({
                icon:"success",
                title:"Deleted",
                text:data.message
            }).then(()=>location.reload());

        }else{

            Swal.fire("Error",data.message,"error");

        }

    });

});
}

const searchInput = document.getElementById("departmentSearch");
const departmentFilter = document.getElementById("departmentFilter");


function filterDepartments(){


    const searchValue = searchInput.value.toLowerCase();

    const filterValue = departmentFilter.value.toLowerCase();



    // FILTER CARDS

    document.querySelectorAll(".department-card").forEach(card=>{


        const department = card.dataset.department;


        const matchesSearch =
        department.includes(searchValue);


        const matchesFilter =
        filterValue === "" ||
        department === filterValue;



        if(matchesSearch && matchesFilter){

            card.style.display="";

        }else{

            card.style.display="none";

        }


    });



    // FILTER TABLE

    document.querySelectorAll("#departmentTable tr").forEach(row=>{


        const department =
row.cells[0].innerText.toLowerCase();


        const matchesSearch =
        department.includes(searchValue);



        const matchesFilter =
        filterValue === "" ||
        department === filterValue;



        if(matchesSearch && matchesFilter){

            row.style.display="";

        }else{

            row.style.display="none";

        }


    });


}



if(searchInput){

    searchInput.addEventListener(
        "keyup",
        filterDepartments
    );

}



if(departmentFilter){

    departmentFilter.addEventListener(
        "change",
        filterDepartments
    );

}

const chart=document.getElementById("departmentStatistics");

if(chart){

new Chart(chart,{

type:"bar",

data:{

labels: departmentLabels,

datasets:[{

label:"Documents",

data: departmentDocuments,

backgroundColor: departmentLabels.map(() => {
    const colors = [
        "#0B5ED7",
        "#198754",
        "#DC3545",
        "#F59E0B",
        "#6F42C1",
        "#20C997",
        "#6610F2",
        "#FD7E14"
    ];

    return colors[Math.floor(Math.random()*colors.length)];
})

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
function viewDepartment(id){


fetch("api/departments/details.php?id="+id)


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



let employees="";


data.employees.forEach(emp=>{

employees += `
<tr>

<td>${emp.name}</td>

<td>${emp.email}</td>

<td>${emp.role}</td>

</tr>
`;

});



let documents="";


data.documents.forEach(doc=>{

documents += `

<li>
${doc.title}
</li>

`;

});



let activities="";


data.activities.forEach(act=>{

activities += `

<li>
${act.activity}
<br>
<small>${act.created_at}</small>
</li>

`;

});



Swal.fire({


title:data.department.name,


width:800,


html:`


<div class="department-details">


<p>
<strong>Head:</strong>
${data.department.head_name ?? '-'}
</p>


<p>
<strong>Email:</strong>
${data.department.email ?? '-'}
</p>


<p>
<strong>Phone:</strong>
${data.department.phone ?? '-'}
</p>



<hr>


<h4>Employees</h4>


<table class="details-table">

<tr>

<th>Name</th>

<th>Email</th>

<th>Role</th>

</tr>


${employees}


</table>



<hr>


<h4>Recent Documents</h4>

<ul>

${documents || "<li>No documents</li>"}

</ul>



<hr>


<h4>Activity</h4>


<ul>

${activities || "<li>No activity</li>"}

</ul>


</div>


`,

confirmButtonText:"Close"


});


});


}