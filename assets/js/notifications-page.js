document.addEventListener("DOMContentLoaded", () => {

    document.addEventListener("click", function (e) {

        const del = e.target.closest(".delete-notification");

        if (!del) return;

        const id = del.dataset.id;

        Swal.fire({
            title: "Delete Notification?",
            text: "This notification will be permanently removed.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Delete",
            cancelButtonText: "Cancel",
            confirmButtonColor: "#dc3545"
        }).then(result => {

            if (!result.isConfirmed) return;

            fetch("api/notifications/delete.php", {

                method: "POST",

                headers: {
                    "Content-Type":"application/x-www-form-urlencoded"
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

                // Remove the card immediately
                const card = del.closest(".notification-card");

                if(card){

                    card.remove();

                }

                // Empty state
                const container =
                    document.getElementById("notificationContainer");

                if(
                    container &&
                    container.querySelectorAll(".notification-card").length===0
                ){

                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fa fa-bell-slash"></i>
                            <h3>No Notifications</h3>
                            <p>You don't have any notifications yet.</p>
                        </div>
                    `;

                }

                // Refresh dropdown badge
                if(typeof loadNotifications==="function"){

                    loadNotifications();

                }

                Swal.fire({
                    icon:"success",
                    title:"Deleted",
                    text:"Notification deleted successfully.",
                    timer:1500,
                    showConfirmButton:false
                });

            });

        });

    });

});