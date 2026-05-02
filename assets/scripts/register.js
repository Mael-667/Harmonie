let fileInput = document.getElementById("registration_form_pdp");
fileInput.addEventListener("change", () => {
    fileInput.previousSibling.style.background = `url(${URL.createObjectURL(fileInput.files[0])})`;
    fileInput.previousSibling.style.color = `#ffffff00`;
})
