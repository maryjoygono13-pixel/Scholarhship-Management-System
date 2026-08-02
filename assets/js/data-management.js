// ===========================
// IMPORT GRADES
// ===========================

const gradeFile = document.getElementById("gradeFile");
const gradeBtn = document.getElementById("gradeBtn");
const gradeFileName = document.getElementById("gradeFileName");
const gradeDeleteBtn = document.getElementById("gradeDeleteBtn");

if (gradeBtn && gradeFile && gradeFileName && gradeDeleteBtn) {

    gradeBtn.addEventListener("click", function () {
        gradeFile.click();
    });

    gradeFile.addEventListener("change", function () {

        if (this.files.length > 0) {
            gradeFileName.textContent = this.files[0].name;
            gradeDeleteBtn.style.display = "block";
        } else {
            gradeFileName.textContent = "No file selected";
            gradeDeleteBtn.style.display = "none";
        }

    });

    gradeDeleteBtn.addEventListener("click", function () {
        gradeFile.value = "";
        gradeFileName.textContent = "No file selected";
        gradeDeleteBtn.style.display = "none";
    });

}
// ===========================
// IMPORT ENROLLMENT
// ===========================

const enrollmentFile = document.getElementById("enrollmentFile");
const enrollmentBtn = document.getElementById("enrollmentBtn");
const enrollmentFileName = document.getElementById("enrollmentFileName");
const enrollmentDeleteBtn = document.getElementById("enrollmentDeleteBtn");

if (enrollmentBtn && enrollmentFile && enrollmentFileName && enrollmentDeleteBtn) {

    enrollmentBtn.addEventListener("click", function () {
        enrollmentFile.click();
    });

    enrollmentFile.addEventListener("change", function () {

        if (this.files.length > 0) {
            enrollmentFileName.textContent = this.files[0].name;
            enrollmentDeleteBtn.style.display = "block";
        } else {
            enrollmentFileName.textContent = "No file selected";
            enrollmentDeleteBtn.style.display = "none";
        }

    });

    enrollmentDeleteBtn.addEventListener("click", function () {
        enrollmentFile.value = "";
        enrollmentFileName.textContent = "No file selected";
        enrollmentDeleteBtn.style.display = "none";
    });

}