define(['jquery','TYPO3/CMS/Xlsimport/Importer'],function($,Importer) {
    var counter = document.querySelector('.counter');
    var OnChangeCheckbox = function(event) {
        var checkbox = event.target;
        var current = parseInt((counter.innerText || counter.textContent));
        if (checkbox.checked) {
            counter.innerHTML = (current + 1).toString();
        } else {
            counter.innerHTML = (current - 1).toString();
        }
    };
    var countElements = document.querySelectorAll('.count');
    Array.prototype.forEach.call(countElements, function (countElement) {
        countElement.addEventListener('change', OnChangeCheckbox);
    });
});
