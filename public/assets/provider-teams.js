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

        var setRowDirty = function (row, dirty) {
            var flag = row.querySelector(".patient-dirty-flag");
            if (flag) {
                flag.value = dirty ? "1" : "0";
            }
            if (dirty) {
                row.classList.add("visit-row-unsaved");
            } else {
                row.classList.remove("visit-row-unsaved");
            }
        };

        var anyRowDirty = function () {
            return visitForm.querySelector(".patient-dirty-flag[value=\"1\"]") !== null;
        };

        visitForm.querySelectorAll(".visit-patient-row").forEach(function (row) {
            row.querySelectorAll(".time-input, .missed-toggle").forEach(function (input) {
                var markDirty = function () {
                    setRowDirty(row, true);
                };
                input.addEventListener("change", markDirty);
                input.addEventListener("input", markDirty);
            });
        });

        window.addEventListener("beforeunload", function (event) {
            if (!anyRowDirty()) {
                return;
            }
            event.preventDefault();
            event.returnValue = "";
        });

        var saveRowViaFetch = function (pid, row) {
            var formData = new FormData(visitForm);
            visitForm.querySelectorAll(".patient-dirty-flag").forEach(function (flag) {
                formData.set(flag.name, "0");
            });
            var rowFlag = row.querySelector(".patient-dirty-flag");
            if (rowFlag) {
                formData.set(rowFlag.name, "1");
            }
            var postUrl = visitForm.getAttribute("action") || window.location.href;
            return fetch(postUrl, {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                redirect: "follow",
            }).then(function (response) {
                if (response.ok && response.url.indexOf("notice=saved") !== -1) {
                    setRowDirty(row, false);
                    return;
                }
                throw new Error("save_failed");
            });
        };

        visitForm.querySelectorAll(".save-patient-row").forEach(function (button) {
            button.addEventListener("click", function () {
                var pid = button.getAttribute("data-pid");
                var row = visitForm.querySelector('.visit-patient-row[data-pid="' + pid + '"]');
                if (!row) {
                    return;
                }
                if (row.querySelector(".patient-dirty-flag") && row.querySelector(".patient-dirty-flag").value !== "1") {
                    setRowDirty(row, true);
                }
                button.disabled = true;
                saveRowViaFetch(pid, row)
                    .catch(function () {
                        window.alert("Could not save this row. Fix any errors and try again.");
                    })
                    .finally(function () {
                        button.disabled = false;
                    });
            });
        });
    }
}());
