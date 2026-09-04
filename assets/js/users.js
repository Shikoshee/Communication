console.log("Users JS Loaded");


// ======================================
// ADD USER
// ======================================

function addUser(){

    Swal.fire({

        title: "Add User",

        html: `

            <input
                id="firstName"
                class="swal2-input"
                placeholder="First Name">

            <input
                id="lastName"
                class="swal2-input"
                placeholder="Last Name">

            <input
                id="username"
                class="swal2-input"
                placeholder="Username">

            <input
                id="email"
                type="email"
                class="swal2-input"
                placeholder="Email">

            <select
                id="department"
                class="swal2-select">

                <option value="">
                    Loading departments...
                </option>

            </select>

            <select
                id="role"
                class="swal2-select">

                <option value="user">
                    User
                </option>

                <option value="manager">
                    Manager
                </option>

                <option value="admin">
                    Administrator
                </option>

            </select>

            <select
                id="status"
                class="swal2-select">

                <option value="active">
                    Active
                </option>

                <option value="inactive">
                    Inactive
                </option>

            </select>

        `,

        didOpen: () => {

            fetch("api/departments/list.php")

                .then(response => {

                    if(!response.ok){

                        throw new Error(
                            "Unable to load departments."
                        );

                    }

                    return response.text();

                })

                .then(text => {

                    let data;

                    try {

                        data = JSON.parse(text);

                    } catch(error) {

                        console.error(
                            "Department API response:",
                            text
                        );

                        throw new Error(
                            "Department service returned an invalid response."
                        );

                    }

                    /*
                     * Support either:
                     *
                     * [
                     *   {id:1,name:"HR"}
                     * ]
                     *
                     * or:
                     *
                     * {
                     *   success:true,
                     *   departments:[...]
                     * }
                     */

                    const departments =
                        Array.isArray(data)
                            ? data
                            : (data.departments || []);

                    const select =
                        document.getElementById("department");

                    if(!departments.length){

                        select.innerHTML = `
                            <option value="">
                                No departments available
                            </option>
                        `;

                        return;

                    }

                    let html = `
                        <option value="">
                            Select Department
                        </option>
                    `;

                    departments.forEach(dept => {

                        html += `
                            <option value="${dept.id}">
                                ${dept.name}
                            </option>
                        `;

                    });

                    select.innerHTML = html;

                })

                .catch(error => {

                    console.error(error);

                    document.getElementById("department").innerHTML = `
                        <option value="">
                            Unable to load departments
                        </option>
                    `;

                });

        },

        showCancelButton: true,

        confirmButtonText: "Create User",

        showLoaderOnConfirm: true,

        allowOutsideClick: () => !Swal.isLoading(),

        preConfirm: () => {

            const firstName =
                document.getElementById("firstName").value.trim();

            const lastName =
                document.getElementById("lastName").value.trim();

            const username =
                document.getElementById("username").value.trim();

            const email =
                document.getElementById("email").value.trim();

            const department =
                document.getElementById("department").value;

            const role =
                document.getElementById("role").value;

            const status =
                document.getElementById("status").value;


            /*
             * Client-side validation
             */

            if(!firstName){

                Swal.showValidationMessage(
                    "First name is required."
                );

                return false;

            }


            if(!lastName){

                Swal.showValidationMessage(
                    "Last name is required."
                );

                return false;

            }


            if(!username){

                Swal.showValidationMessage(
                    "Username is required."
                );

                return false;

            }


            if(!email){

                Swal.showValidationMessage(
                    "Email address is required."
                );

                return false;

            }


            if(!department){

                Swal.showValidationMessage(
                    "Please select a department."
                );

                return false;

            }


            return fetch(
                "api/users/create.php",
                {

                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded; charset=UTF-8"
                    },

                    body: new URLSearchParams({

                        first_name: firstName,

                        last_name: lastName,

                        username: username,

                        email: email,

                        department_id: department,

                        role: role,

                        status: status

                    })

                }
            )

            /*
             * Read TEXT first.
             *
             * This prevents:
             * Unexpected token '<'
             */

            .then(response => {

                return response.text().then(text => {

                    console.log(
                        "Create user response:",
                        text
                    );

                    let data;

                    try {

                        data = JSON.parse(text);

                    } catch(error) {

                        console.error(
                            "Invalid JSON returned by create.php:",
                            text
                        );

                        throw new Error(
                            "The server returned an invalid response. Check the PHP error log."
                        );

                    }

                    if(!response.ok){

                        throw new Error(
                            data.message ||
                            "Unable to create user."
                        );

                    }

                    return data;

                });

            })

            .catch(error => {

                console.error(
                    "Create user error:",
                    error
                );

                Swal.showValidationMessage(
                    error.message
                );

                return false;

            });

        }

    })

    .then(result => {

        if(!result.isConfirmed){

            return;

        }


        /*
         * preConfirm returns the server response.
         */

        const data = result.value;


        if(!data){

            return;

        }


        if(data.success){

            Swal.fire({

                title: "User Created",

                html:
                    data.message +
                    "<br><br>" +
                    "<strong>Temporary Password:</strong> " +
                    data.temporary_password,

                icon: "success",

                confirmButtonText: "OK"

            }).then(() => {

                location.reload();

            });

        } else {

            Swal.fire(

                "Error",

                data.message ||
                "Unable to create the user.",

                "error"

            );

        }

    });

}





// ======================================
// EDIT USER
// ======================================

function editUser(id){

fetch("api/users/get.php?id="+id)

.then(r=>r.json())

.then(result=>{

if(!result.success){

Swal.fire("Error",result.message,"error");

return;

}

const u=result.user;

Swal.fire({

title:"Edit User",

html:`

<input
id="firstName"
class="swal2-input"
value="${u.first_name}">

<input
id="lastName"
class="swal2-input"
value="${u.last_name}">

<input
id="username"
class="swal2-input"
value="${u.username}">

<input
id="email"
class="swal2-input"
value="${u.email}">

<select
id="role"
class="swal2-select">

<option value="user">User</option>
<option value="manager">Manager</option>
<option value="admin">Administrator</option>

</select>

<select
id="status"
class="swal2-select">

<option value="active">Active</option>
<option value="inactive">Inactive</option>
<option value="locked">Locked</option>

</select>

`,

didOpen:()=>{

document.getElementById("role").value=u.role;
document.getElementById("status").value=u.status;

},

showCancelButton:true,

confirmButtonText:"Save Changes",

preConfirm:()=>{

return{

id:id,

first_name:document.getElementById("firstName").value,

last_name:document.getElementById("lastName").value,

username:document.getElementById("username").value,

email:document.getElementById("email").value,

role:document.getElementById("role").value,

status:document.getElementById("status").value

};

}

}).then(form=>{

if(!form.isConfirmed) return;

fetch("api/users/update.php",{

method:"POST",

headers:{
"Content-Type":"application/x-www-form-urlencoded"
},

body:new URLSearchParams(form.value)

})

.then(r=>r.json())

.then(data=>{

if(data.success){

Swal.fire(
"Updated",
data.message,
"success"
).then(()=>location.reload());

}else{

Swal.fire(
"Error",
data.message,
"error"
);

}

});

});

});

}



// ======================================
// DELETE USER
// ======================================

function deleteUser(id,name){

Swal.fire({

title:"Delete User?",

text:"Delete "+name+"?",

icon:"warning",

showCancelButton:true,

confirmButtonColor:"#dc3545",

confirmButtonText:"Delete"

}).then(result=>{

if(!result.isConfirmed) return;

fetch("api/users/delete.php",{

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

Swal.fire(
"Deleted",
data.message,
"success"
).then(()=>location.reload());

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
// RESET PASSWORD
// ======================================

function resetPassword(id){

Swal.fire({

title:"Reset Password?",

text:"A temporary password will be generated.",

icon:"question",

showCancelButton:true,

confirmButtonText:"Reset"

}).then(result=>{

if(!result.isConfirmed) return;

fetch("api/users/reset-password.php",{

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

Swal.fire(
"Password Reset",
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
// LOCK / UNLOCK
// ======================================

function lockUser(id){

fetch("api/users/toggle-lock.php",{

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

Swal.fire(
"Success",
data.message,
"success"
).then(()=>location.reload());

}else{

Swal.fire(
"Error",
data.message,
"error"
);

}

});

}



// ======================================
// SEARCH + FILTER
// ======================================

const search=document.getElementById("userSearch");
const filter=document.getElementById("departmentFilter");

function filterUsers(){

const searchValue=search.value.toLowerCase();
const department=filter.value;

document.querySelectorAll("#usersTable tr").forEach(row=>{

const name=row.dataset.name || "";
const dept=row.dataset.department || "";

const matchesSearch=name.includes(searchValue);

const matchesDepartment=
department==="" || department===dept;

row.style.display=
(matchesSearch && matchesDepartment)
?
""
:
"none";

});

}

if(search){

search.addEventListener(
"keyup",
filterUsers
);

}

if(filter){

filter.addEventListener(
"change",
filterUsers
);

}



// ======================================
// CHART
// ======================================

const chart=document.getElementById("userChart");

if(chart){

new Chart(chart,{

type:"bar",

data:{

labels:userChartLabels,

datasets:[{

label:"Users",

data:userChartValues,

backgroundColor:[

"#0B5ED7",
"#198754",
"#DC3545",
"#F59E0B",
"#6F42C1",
"#20C997",
"#FD7E14",
"#6610F2"

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
function viewUser(id){

fetch("api/users/details.php?id="+id)

.then(r=>r.json())

.then(data=>{

if(!data.success){

Swal.fire(
"Error",
data.message,
"error"
);

return;

}

const u=data.user;

let documents="";

data.documents.forEach(doc=>{

documents+=`

<li>

${doc.title}

<br>

<small>${doc.created_at}</small>

</li>

`;

});

let activities="";

data.activities.forEach(activity=>{

activities+=`

<li>

${activity.activity}

<br>

<small>${activity.created_at}</small>

</li>

`;

});

Swal.fire({

title:

`${u.first_name} ${u.last_name}`,

width:850,

html:`

<div class="user-details">

<img
src="${u.profile_photo || 'assets/images/default-user.png'}"
class="details-photo">

<p><strong>Username:</strong> ${u.username}</p>

<p><strong>Email:</strong> ${u.email}</p>

<p><strong>Phone:</strong> ${u.phone ?? "-"}</p>

<p><strong>Department:</strong> ${u.department_name ?? "-"}</p>

<p><strong>Role:</strong> ${u.role}</p>

<p><strong>Status:</strong> ${u.status}</p>

<p><strong>Last Login:</strong> ${u.last_login ?? "-"}</p>

<p><strong>Created:</strong> ${u.created_at}</p>

<hr>

<h4>Recent Documents</h4>

<ul>

${documents || "<li>No documents uploaded.</li>"}

</ul>

<hr>

<h4>Recent Activity</h4>

<ul>

${activities || "<li>No recent activity.</li>"}

</ul>

</div>

`,

confirmButtonText:"Close"

});

});

}
