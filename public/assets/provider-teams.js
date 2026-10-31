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
        var saveRowLabel = visitForm.getAttribute("data-save-row-label") || "Save row";
        var retryRowLabel = visitForm.getAttribute("data-retry-row-label") || "Retry";
        var saveFailedMessage = visitForm.getAttribute("data-row-save-failed")
            || "Could not save this row. Fix any errors and try again.";
        var suppressBeforeUnload = false;
        var staffDirty = document.getElementById("staff-dirty");

        visitForm.addEventListener("submit", function () {
            suppressBeforeUnload = true;
        });

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

        var rowErrorBox = function (row) {
            return row.querySelector(".visit-row-error");
        };

        var clearRowError = function (row, button) {
            var box = rowErrorBox(row);
            if (box) {
                box.textContent = "";
            }
            if (button) {
                button.textContent = saveRowLabel;
            }
        };

        var showRowError = function (row, messages, button) {
            var box = rowErrorBox(row);
            if (box) {
                box.textContent = messages.join(" ");
            }
            setRowDirty(row, true);
            if (button) {
                button.textContent = retryRowLabel;
            }
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
            if (suppressBeforeUnload || !anyRowDirty()) {
                return;
            }
            event.preventDefault();
            event.returnValue = "";
        });

        var saveRowViaFetch = function (row, button) {
            var formData = new FormData(visitForm);
            formData.set("save_mode", "row");
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
                headers: {
                    Accept: "application/json",
                },
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (data && data.ok === true) {
                    clearRowError(row, button);
                    setRowDirty(row, false);
                    return;
                }
                var errors = data && Array.isArray(data.errors) ? data.errors : [saveFailedMessage];
                showRowError(row, errors, button);
            });
        };

        visitForm.querySelectorAll(".save-patient-row").forEach(function (button) {
            button.addEventListener("click", function () {
                var pid = button.getAttribute("data-pid");
                var row = visitForm.querySelector('.visit-patient-row[data-pid="' + pid + '"]');
                if (!row) {
                    return;
                }
                var flag = row.querySelector(".patient-dirty-flag");
                if (flag && flag.value !== "1") {
                    setRowDirty(row, true);
                }
                button.disabled = true;
                saveRowViaFetch(row, button)
                    .catch(function () {
                        showRowError(row, [saveFailedMessage], button);
                    })
                    .finally(function () {
                        button.disabled = false;
                    });
            });
        });
    }
}());
