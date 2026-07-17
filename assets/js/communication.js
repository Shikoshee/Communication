function sendMessage(){

    const input = document.getElementById("messageInput");

    if(input.value.trim() === ""){

        Swal.fire({
            icon:"warning",
            title:"Empty Message",
            text:"Please type a message first."
        });

        return;
    }

    Swal.fire({
        icon:"success",
        title:"Message Sent",
        text:"Your message has been delivered."
    });

    input.value = "";

}

function attachDocument(){

    Swal.fire({
        icon:"info",
        title:"Attach Document",
        text:"This will open the document library once the backend is connected."
    });

}

document.getElementById("conversationSearch").addEventListener("keyup", function(){

    let value = this.value.toLowerCase();

    document.querySelectorAll(".conversation").forEach(function(item){

        let text = item.innerText.toLowerCase();

        item.style.display = text.includes(value) ? "flex" : "none";

    });

});