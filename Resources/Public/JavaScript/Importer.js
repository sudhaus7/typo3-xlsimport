define(['jquery','TYPO3/CMS/Xlsimport/Importer'],function($,Importer) {
    var counter = document.getElementsByClassName('counter')[0];
    var OnChangeCheckbox = function(event) {
        var checkbox = event.target;
        var current = parseInt((counter.innerText || counter.textContent));
        if (checkbox.checked) {
            counter.innerHTML = (current + 1).toString();
        } else {
            counter.innerHTML = (current - 1).toString();
        }
    };
    var countElements = document.getElementsByClassName('count');
    for (var i = 0; i < countElements.length;i++) {
        var el = countElements[i];
        el.addEventListener ("CheckboxStateChange", OnChangeCheckbox, false);
    }
});