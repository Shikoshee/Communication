console.log("Departments JS loaded - NEW VERSION");

function addDepartment(){

    let headOptions = `
        <option value="">
            Select Department Head
        </option>
    `;

    if(Array.isArray(departmentHeads)){

        departmentHeads.forEach(head => {

            const fullName =
                `${head.first_name || ""} ${head.last_name || ""}`.trim();

            const role =
                head.role === "administrator"
                    ? "Admin"
                    : (
                        head.role
                            ? head.role.charAt(0).toUpperCase() +
                              head.role.slice(1)
                            : ""
                    );

            headOptions += `
                <option value="${head.id}">
                    ${fullName} (${role})
                </option>
            `;

        });

    }


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
                id="departmentHead"
                class="swal2-select">

                ${headOptions}

            </select>

            <select
                id="departmentStatus"
                class="swal2-select">

                <option value="active">
                    Active
                </option>

                <option value="inactive">
                    Inactive
                </option>

            </select>

        `,

        focusConfirm:false,

        showCancelButton:true,

        confirmButtonText:"Save",

        preConfirm:()=>{

            const name =
                document
                    .getElementById("departmentName")
                    .value
                    .trim();

            const description =
                document
                    .getElementById("departmentDescription")
                    .value
                    .trim();

            const head_id =
                document
                    .getElementById("departmentHead")
                    .value;

            const status =
                document
                    .getElementById("departmentStatus")
                    .value;


            if(!name){

                Swal.showValidationMessage(
                    "Department name is required."
                );

                return false;

            }


            return {

                name:name,

                description:description,

                head_id:head_id,

                status:status

            };

        }

    }).then(result=>{

        if(result.isConfirmed){

            saveDepartment(result.value);

        }

    });

}

function editDepartment(id){

    fetch("api/departments/get.php?id=" + encodeURIComponent(id))

    .then(response => {

        if(!response.ok){
            throw new Error("HTTP " + response.status);
        }

        return response.json();

    })

    .then(result => {

        console.log("Department API response:", result);

        if(!result.success){

            Swal.fire(
                "Error",
                result.message || "Unable to load department.",
                "error"
            );

            return;
        }


        const d = result.department;


        console.log("Department being edited:", d);


        /*
         * ==========================================
         * BUILD DEPARTMENT HEAD OPTIONS
         * ==========================================
         */

        let headOptions = `
            <option value="">
                No Department Head
            </option>
        `;


        if(
            typeof departmentHeads !== "undefined" &&
            Array.isArray(departmentHeads)
        ){

            departmentHeads.forEach(head => {

                const fullName =
                    `${head.first_name || ""} ${head.last_name || ""}`
                    .trim();


                let role = head.role || "";

                if(role === "administrator"){
                    role = "Admin";
                }else{
                    role =
                        role.charAt(0).toUpperCase() +
                        role.slice(1);
                }


                headOptions += `
                    <option value="${Number(head.id)}">
                        ${fullName} (${role})
                    </option>
                `;

            });

        }


        /*
         * ==========================================
         * OPEN EDIT MODAL
         * ==========================================
         */

        Swal.fire({

            title: "Edit Department",

            width: 600,

            html: `

                <input
                    id="deptName"
                    class="swal2-input"
                    placeholder="Department Name"
                    value="${escapeHtml(d.name || "")}"
                >


                <textarea
                    id="deptDescription"
                    class="swal2-textarea"
                    placeholder="Description"
                >${escapeHtml(d.description || "")}</textarea>


                <select
                    id="deptHead"
                    class="swal2-select"
                >

                    ${headOptions}

                </select>


                <select
                    id="deptStatus"
                    class="swal2-select"
                >

                    <option value="active">
                        Active
                    </option>

                    <option value="inactive">
                        Inactive
                    </option>

                </select>

            `,

            focusConfirm: false,

            showCancelButton: true,

            confirmButtonText: "Save Changes",

            cancelButtonText: "Cancel",


            didOpen: () => {

                const headSelect =
                    document.getElementById("deptHead");


                const statusSelect =
                    document.getElementById("deptStatus");


                /*
                 * Set current department head
                 */

                if(headSelect){

                    headSelect.value =
                        d.head_id !== null &&
                        d.head_id !== undefined &&
                        d.head_id !== ""
                            ? String(d.head_id)
                            : "";

                }


                /*
                 * Set current status
                 */

                if(statusSelect){

                    statusSelect.value =
                        String(
                            d.status || "active"
                        ).toLowerCase();

                }


                console.log(
                    "Selected head:",
                    headSelect ? headSelect.value : null
                );

                console.log(
                    "Selected status:",
                    statusSelect ? statusSelect.value : null
                );

            },


            preConfirm: () => {

                const name =
                    document
                        .getElementById("deptName")
                        .value
                        .trim();


                const description =
                    document
                        .getElementById("deptDescription")
                        .value
                        .trim();


                const headId =
                    document
                        .getElementById("deptHead")
                        .value;


                const status =
                    document
                        .getElementById("deptStatus")
                        .value;


                if(!name){

                    Swal.showValidationMessage(
                        "Department name is required."
                    );

                    return false;

                }


                return {

                    id: id,

                    name: name,

                    description: description,

                    head_id: headId,

                    status: status

                };

            }

        })

        .then(result => {

            if(!result.isConfirmed){
                return;
            }


            console.log(
                "Updating department:",
                result.value
            );


            fetch(
                "api/departments/update.php",
                {

                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded"
                    },

                    body:
                        new URLSearchParams(
                            result.value
                        )

                }

            )

            .then(response => {

                if(!response.ok){
                    throw new Error(
                        "HTTP " + response.status
                    );
                }

                return response.json();

            })

            .then(data => {

                console.log(
                    "Update response:",
                    data
                );


                if(data.success){

                    Swal.fire({

                        icon: "success",

                        title: "Updated",

                        text:
                            data.message ||
                            "Department updated successfully."

                    })

                    .then(() => {

                        location.reload();

                    });

                }else{

                    Swal.fire(
                        "Error",
                        data.message ||
                            "Unable to update department.",
                        "error"
                    );

                }

            })

            .catch(error => {

                console.error(
                    "Update department error:",
                    error
                );

                Swal.fire(
                    "Error",
                    "Unable to update department.",
                    "error"
                );

            });

        });

    })

    .catch(error => {

        console.error(
            "Get department error:",
            error
        );

        Swal.fire(
            "Error",
            "Unable to load department.",
            "error"
        );

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
function saveDepartment(data){

    fetch("api/departments/create.php",{
        method:"POST",
        headers:{
            "Content-Type":"application/x-www-form-urlencoded"
        },
        body:new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(result => {

        if(result.success){

            Swal.fire(
                "Success",
                result.message,
                "success"
            ).then(()=>{
                location.reload();
            });

        }else{

            Swal.fire(
                "Error",
                result.message,
                "error"
            );

        }

    })
    .catch(error=>{
        console.error(error);
        Swal.fire(
            "Error",
            "Failed to contact server.",
            "error"
        );
    });

}
function escapeHtml(value){

    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

}