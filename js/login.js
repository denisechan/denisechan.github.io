
function validateForm() {
    var x = document.forms["login"]["username"].value;
    if (x == null || x == "") {
        alert("Please enter username");
        return false;
    }
}