console.log("Notifications JS loaded");


/*
|--------------------------------------------------------------------------
| Global Notification Loader
|--------------------------------------------------------------------------
*/

function loadNotifications() {


    const count = document.getElementById("notificationCount");
    const list = document.getElementById("notificationList");


    if(!list){

        return;

    }


    fetch("api/notifications/get.php")


    .then(response => response.text())


    .then(text => {


        let data;


        try{

            data = JSON.parse(text);

        }

        catch(error){

            console.error(
                "Notification JSON Error:",
                text
            );

            return;

        }



        if(!data.success){


            list.innerHTML = `

            <div class="empty-notification">

                No notifications

            </div>

            `;


            return;

        }



        /*
        |--------------------------------------------------------------------------
        | Notification Count
        |--------------------------------------------------------------------------
        */


        if(count){


            count.textContent = data.count;


            count.style.display =

                data.count > 0

                ?

                "flex"

                :

                "none";


        }




        /*
        |--------------------------------------------------------------------------
        | Empty State
        |--------------------------------------------------------------------------
        */


        if(!data.notifications.length){


            list.innerHTML = `

            <div class="empty-notification">

                No notifications

            </div>

            `;


            return;


        }



        /*
        |--------------------------------------------------------------------------
        | Build Dropdown
        |--------------------------------------------------------------------------
        */


        let html = "";



        data.notifications.forEach(notification => {


            html += `

            <div 
                class="notification-item ${notification.is_read == 0 ? 'unread':''}"
                data-id="${notification.id}"
            >


                <div class="notification-icon">

                    <i class="fas fa-bell"></i>

                </div>



                <div class="notification-content">


                    <strong>

                        ${escapeHTML(notification.title)}

                    </strong>



                    <p>

                        ${escapeHTML(notification.message)}

                    </p>



                    <small>

                        ${notification.time}

                    </small>



                    <div class="notification-actions">


                        <button
                            class="mark-read"
                            data-id="${notification.id}"
                            title="Mark as read"
                        >

                            <i class="fas fa-check"></i>

                        </button>




                        <button
                            class="delete-notification"
                            data-id="${notification.id}"
                            title="Delete"
                        >

                            <i class="fas fa-trash"></i>

                        </button>


                    </div>


                </div>


            </div>

            `;


        });



        list.innerHTML = html;



    })


    .catch(error => {

        console.error(
            "Notification Load Error:",
            error
        );

    });


}





/*
|--------------------------------------------------------------------------
| Escape HTML
|--------------------------------------------------------------------------
*/

function escapeHTML(value){


    const div = document.createElement("div");

    div.textContent = value ?? "";

    return div.innerHTML;


}





/*
|--------------------------------------------------------------------------
| Page Ready
|--------------------------------------------------------------------------
*/

document.addEventListener(
"DOMContentLoaded",
()=>{


    const bell =
        document.getElementById("notificationBell");


    const dropdown =
        document.getElementById("notificationDropdown");


    const markAll =
        document.getElementById("markAllRead");



    /*
    |--------------------------------------------------------------------------
    | Toggle Dropdown
    |--------------------------------------------------------------------------
    */


    if(bell && dropdown){


        bell.addEventListener(
        "click",
        function(e){


            e.preventDefault();

            e.stopPropagation();


            dropdown.classList.toggle("show");


        });


        dropdown.addEventListener(
        "click",
        function(e){

            e.stopPropagation();

        });



        document.addEventListener(
        "click",
        function(){

            dropdown.classList.remove("show");

        });


    }




    /*
    |--------------------------------------------------------------------------
    | Mark One / Delete Notification
    |--------------------------------------------------------------------------
    */


    document.addEventListener(
    "click",
    function(e){



        const mark =
            e.target.closest(".mark-read");



        if(mark){


            const id = mark.dataset.id;



            fetch(
            "api/notifications/mark_read.php",
            {

                method:"POST",

                headers:{

                    "Content-Type":
                    "application/x-www-form-urlencoded"

                },

                body:

                "id="+encodeURIComponent(id)

            })

            .then(r=>r.json())

            .then(data=>{


                console.log(
                    "Mark read:",
                    data
                );


                loadNotifications();


            });



        }





        const del =
            e.target.closest(".delete-notification");



        if(del){


            const id = del.dataset.id;



            const del = e.target.closest(".delete-notification");

if (del) {

    const id = del.dataset.id;

    Swal.fire({
        title: "Delete Notification?",
        text: "This notification will be permanently removed.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        confirmButtonText: "Delete",
        cancelButtonText: "Cancel"
    }).then(result => {

        if (!result.isConfirmed) return;

        fetch("api/notifications/delete.php", {

            method: "POST",

            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },

            body: "id=" + encodeURIComponent(id)

        })
        .then(r => r.json())
        .then(data => {

            if (!data.success) {

                Swal.fire(
                    "Error",
                    data.message,
                    "error"
                );

                return;
            }

            // Remove notification from Notifications page
            const card = document.querySelector(
                `.notification-card[data-id="${id}"]`
            );

            if (card) {

                card.remove();

                // Show empty state if nothing remains
                const container =
                    document.getElementById("notificationContainer");

                if (
                    container &&
                    container.querySelectorAll(".notification-card").length === 0
                ) {

                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fa fa-bell-slash"></i>
                            <h3>No Notifications</h3>
                            <p>You don't have any notifications yet.</p>
                        </div>
                    `;

                }

            }

            // Refresh dropdown and badge
            loadNotifications();

            Swal.fire({
                icon: "success",
                title: "Deleted",
                text: "Notification deleted successfully.",
                timer: 1500,
                showConfirmButton: false
            });

        });

    });

}



    /*
    |--------------------------------------------------------------------------
    | Mark All
    |--------------------------------------------------------------------------
    */


    if(markAll){


        markAll.addEventListener(
        "click",
        function(){



            fetch(
            "api/notifications/mark_all_read.php",
            {

                method:"POST"

            })


            .then(r=>r.json())


            .then(data=>{


                console.log(
                    "Mark all:",
                    data
                );


                loadNotifications();


            });



        });


    }




    /*
    |--------------------------------------------------------------------------
    | Initial Load
    |--------------------------------------------------------------------------
    */


    loadNotifications();



    /*
    |--------------------------------------------------------------------------
    | Auto Refresh
    |--------------------------------------------------------------------------
    */


    setInterval(
        loadNotifications,
        10000
    );



});