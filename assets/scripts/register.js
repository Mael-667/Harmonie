let fileInput = document.getElementById("registration_form_pdp");
fileInput.addEventListener("change", () => {
    fileInput.previousSibling.style.background = `url(${URL.createObjectURL(fileInput.files[0])})`;
    fileInput.previousSibling.style.color = `#ffffff00`;
})

const ws = new WebSocket("ws://127.0.0.1:8080");

ws.onopen = (e) => {
  console.log(e);
  
  ws.send("euh")
};

ws.onclose = (e) => {
  console.log("deconnecté");
  
};

ws.onmessage = (e) => {
  console.log(e.data);
};