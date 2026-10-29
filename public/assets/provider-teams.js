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
}());
