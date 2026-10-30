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
}());
