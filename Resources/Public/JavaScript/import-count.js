"use strict";

class XlsImportCounter {
  static counter;
  countElements;

  constructor() {
    XlsImportCounter.counter = document.querySelector('.counter');
    this.countElements = document.querySelectorAll('.count');
    Array.prototype.forEach.call(this.countElements, function (countElement) {
      countElement.addEventListener('change', XlsImportCounter.OnChangeCheckbox);
    })
  }
  static OnChangeCheckbox = function(event) {
    const checkbox = event.target;
    let current = parseInt((XlsImportCounter.counter.innerText || XlsImportCounter.counter.textContent));
    if (checkbox.checked) {
      XlsImportCounter.counter.innerHTML = (current + 1).toString();
    } else {
      XlsImportCounter.counter.innerHTML = (current - 1).toString();
    }
  };
}

export default new XlsImportCounter();
