document.addEventListener("DOMContentLoaded", function(){

    loadPermissions();

});


function loadPermissions(){

    fetch("api/permissions/list.php")
    .then(response => response.json())
    .then(result => {
        if(result.success){
            let table = document.getElementById(
                "permissionsTable"
            );
            table.innerHTML = "";
            result.data.forEach(user => {
                table.innerHTML += `
                <tr>
                <td>
                    <div class="user-info">
                        <div>
                            <strong>
                            ${user.first_name}
                            ${user.last_name}
                            </strong>
                            <p>
                            ${user.role}
                            </p>
                        </div>
                    </div>
                </td>
                <td>
                    ${user.department_name ?? 'N/A'}
                </td>
                <td>

                    <input 
class="permission-check"
data-user="${user.id}"
data-permission="can_view"
type="checkbox"
${user.can_view == 1 ? 'checked':''}>
                </td>
                <td>
                    <input 
class="permission-check"
data-user="${user.id}"
data-permission="can_edit"
type="checkbox"
${user.can_edit == 1 ? 'checked':''}>
                </td>
                <td>
                    <input 
class="permission-check"
data-user="${user.id}"
data-permission="can_approve"
type="checkbox"
${user.can_approve == 1 ? 'checked':''}>
                </td>
                <td>

<input
class="permission-check"
data-user="${user.id}"
data-permission="can_delete"
type="checkbox"
${user.can_delete == 1 ? 'checked' : ''}>

</td>

<td>

<input
class="permission-check"
data-user="${user.id}"
data-permission="can_share"
type="checkbox"
${user.can_share == 1 ? 'checked' : ''}>

</td>
               <td>

<button
class="edit-btn"
onclick="editPermission(${user.id})">
<i class="fa fa-edit"></i>
</button>


<button
class="delete-btn"
onclick="deletePermission(${user.id}, '${user.first_name} ${user.last_name}')">
<i class="fa fa-trash"></i>
</button>

</td>
                </td>
                </tr>

                `;


            });


        }


    })

    .catch(error => {

        console.error(
            "Permission loading error:",
            error
        );

    });

}
document.addEventListener(
"change",
function(e){


    if(e.target.classList.contains("permission-check")){

        let checkbox = e.target;

        let formData = new FormData();


        formData.append(
            "user_id",
            checkbox.dataset.user
        );

        formData.append(
            checkbox.dataset.permission,
            checkbox.checked ? 1 : 0
        );



        fetch(
            "api/permissions/update.php",
            {
                method:"POST",
                body:formData
            }
        )

        .then(response=>response.json())

        .then(result=>{


            if(result.success){

                console.log(
                    "Permission updated"
                );

            }
            else{

                alert(
                    result.message
                );

            }


        });


    }


});
function deletePermission(id, name){

    Swal.fire({

        title: "Delete Permissions?",

        text: "Remove all permissions for " + name + "?",

        icon: "warning",

        showCancelButton: true,

        confirmButtonColor: "#dc3545",

        confirmButtonText: "Delete"

    }).then(result => {

        if(!result.isConfirmed) return;

        fetch("api/permissions/delete.php", {

            method: "POST",

            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },

            body: new URLSearchParams({
                user_id: id
            })

        })

        .then(r => r.json())

        .then(data => {

            if(data.success){

                Swal.fire(
                    "Deleted",
                    data.message || "Permissions removed successfully.",
                    "success"
                ).then(() => {

                    loadPermissions();

                });

            }else{

                Swal.fire(
                    "Error",
                    data.message,
                    "error"
                );

            }

        })

        .catch(error => {

            console.error(error);

            Swal.fire(
                "Error",
                "An unexpected error occurred.",
                "error"
            );

        });

    });

}
function editPermission(id){

    fetch("api/permissions/get.php?id=" + id)

    .then(response => response.json())

    .then(result => {

        if(!result.success){

            Swal.fire(
                "Error",
                result.message,
                "error"
            );

            return;

        }

        let p = result.permissions;

        Swal.fire({

            title: "Edit Permissions",

            html: `

            <div style="text-align:left;">

                <label>
                    <input id="can_view"
                    type="checkbox"
                    ${p.can_view == 1 ? "checked" : ""}>
                    View Documents
                </label>

                <br><br>

                <label>
                    <input id="can_edit"
                    type="checkbox"
                    ${p.can_edit == 1 ? "checked" : ""}>
                    Edit Documents
                </label>

                <br><br>

                <label>
                    <input id="can_approve"
                    type="checkbox"
                    ${p.can_approve == 1 ? "checked" : ""}>
                    Approve Documents
                </label>

                <br><br>

                <label>
                    <input id="can_delete"
                    type="checkbox"
                    ${p.can_delete == 1 ? "checked" : ""}>
                    Delete Documents
                </label>

                <br><br>

                <label>
                    <input id="can_share"
                    type="checkbox"
                    ${p.can_share == 1 ? "checked" : ""}>
                    Share Documents
                </label>

            </div>
            `,

            showCancelButton: true,

            confirmButtonText: "Save Changes",

            preConfirm: () => {

                return {

                    user_id: id,

                    can_view:
                    document.getElementById("can_view").checked ? 1 : 0,

                    can_edit:
                    document.getElementById("can_edit").checked ? 1 : 0,

                    can_approve:
                    document.getElementById("can_approve").checked ? 1 : 0,

                    can_delete:
                    document.getElementById("can_delete").checked ? 1 : 0,

                    can_share:
                    document.getElementById("can_share").checked ? 1 : 0

                };

            }

        }).then(form => {

            if(!form.isConfirmed) return;

            fetch("api/permissions/update.php",{

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
                        "Permissions updated successfully.",
                        "success"
                    ).then(()=>{

                        loadPermissions();

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

    });

}
function saveSharing(){

    const documentId =
        document.getElementById("documentSelect").value;


    const users = Array.from(
        document.getElementById("userSelect").selectedOptions
    ).map(option => option.value);


    if(!documentId){

        Swal.fire(
            "Error",
            "Please select a document.",
            "error"
        );

        return;

    }


    if(users.length === 0){

        Swal.fire(
            "Error",
            "Please select at least one user.",
            "error"
        );

        return;

    }


    const formData = new FormData();


    formData.append(
        "document_id",
        documentId
    );


    users.forEach(function(userId){

        formData.append(
            "users[]",
            userId
        );

    });


    formData.append(
        "can_view",
        document.getElementById("shareView").checked ? 1 : 0
    );


    formData.append(
        "can_edit",
        document.getElementById("shareEdit").checked ? 1 : 0
    );


    formData.append(
        "can_share",
        document.getElementById("shareShare").checked ? 1 : 0
    );


    console.log(
        "Sending:",
        Object.fromEntries(formData)
    );


    fetch(
        "api/permissions/share.php",
        {
            method: "POST",
            body: formData
        }
    )

    .then(response => response.json())

    .then(result => {

        console.log(result);


        if(result.success){

            Swal.fire(
                "Saved",
                result.message,
                "success"
            ).then(() => {

                location.reload();

            });

        }
        else{

            Swal.fire(
                "Error",
                result.message,
                "error"
            );

        }

    })

    .catch(error => {

        console.error(
            "Save error:",
            error
        );


        Swal.fire(
            "Error",
            "Server error occurred.",
            "error"
        );

    });

}