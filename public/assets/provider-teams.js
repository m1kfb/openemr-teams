(function () {
    var printButton = document.getElementById("print-matrix");
    if (printButton) {
        printButton.addEventListener("click", function () {
            window.print();
        });
    }

    var toggles = document.querySelectorAll(".missed-toggle");
    toggles.forEach(function (box) {
        var sync = function () {
            var slot = CSS.escape(box.getAttribute("data-missed-for") || "");
            var inputs = document.querySelectorAll('[data-slot="' + slot + '"]');
            inputs.forEach(function (input) {
                input.readOnly = box.checked;
            });
        };
        box.addEventListener("change", sync);
        sync();
    });

    document.querySelectorAll(".provider-teams-pull-care-team").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            var teamName = form.getAttribute("data-team-name") || "";
            var count = form.getAttribute("data-patient-count") || "0";
            var message = "This updates " + teamName + " for " + count + " patients. Continue?";
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    var visitForm = document.getElementById("visit-day-form");
    if (visitForm) {
        var staffDirty = document.getElementById("staff-dirty");
        var markStaffDirty = function () {
            if (staffDirty) {
                staffDirty.value = "1";
            }
        };
        visitForm.querySelectorAll(".staff-on-duty-input").forEach(function (input) {
            input.addEventListener("change", markStaffDirty);
        });

        var markPatientDirty = function (row) {
            var flag = row.querySelector(".patient-dirty-flag");
            if (flag) {
                flag.value = "1";
            }
        };

        visitForm.querySelectorAll(".visit-patient-row").forEach(function (row) {
            row.querySelectorAll(".time-input, .missed-toggle").forEach(function (input) {
                input.addEventListener("change", function () {
                    markPatientDirty(row);
                });
                input.addEventListener("input", function () {
                    markPatientDirty(row);
                });
            });
        });

        visitForm.querySelectorAll(".save-patient-row").forEach(function (button) {
            button.addEventListener("click", function () {
                var pid = button.getAttribute("data-pid");
                visitForm.querySelectorAll(".patient-dirty-flag").forEach(function (flag) {
                    flag.value = "0";
                });
                visitForm.querySelectorAll(".visit-patient-row").forEach(function (row) {
                    if (row.getAttribute("data-pid") === pid) {
                        markPatientDirty(row);
                    }
                });
            });
        });
    }
}());
