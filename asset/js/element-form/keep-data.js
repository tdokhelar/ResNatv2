let form_app = {
  
  form: null,
  loading: false,
  
  init: () => {
    form_app.form = document.querySelector('.element-form form');
    if (form_app.form && window.location.pathname.endsWith('elements/add')) {
      setTimeout(() => {
        form_app.checkForDataInLocalStorage();
        form_app.enableEventListeners();
      }, 100)
    }
  },
  
  enableEventListeners: () => {
    const inputs = form_app.form.querySelectorAll('input, textarea');
    inputs.forEach(input => {
      input.addEventListener('change', (event) => {
        form_app.setDataToLocalStorage(event.target);
      }) 
      if (['text', 'textarea', 'number', 'email', 'url', 'tel'].includes(input.type)) {
        input.addEventListener('keyup', (event) => {
          form_app.setDataToLocalStorage(event.target);
        }) 
      }
    })
    // Date picker
    const datePickerButtons = document.querySelectorAll('.daterangepicker button.applyBtn');
    datePickerButtons.forEach(btn => {
      btn.addEventListener('click', (event) => {
        const dateInputs = form_app.form.querySelectorAll('.field-date input[type="text"]');
        setTimeout(() => {
          dateInputs.forEach(input => {
            form_app.setDataToLocalStorage({
              id: input.id,
              type: input.type,
              value: input.value.replace(' au ', ' - '), //For date ranges
            });
          })
        }, 100)
      }) 
    })
    // Select
    const selectOptions = document.querySelectorAll('.select-input ul.dropdown-content li')
    selectOptions.forEach(selectOption => {
      selectOption.addEventListener('click', (event) => {
        const select = selectOption.closest('.input-field.select-input').querySelector('select');
        form_app.setDataToLocalStorage(select);
      })
    })
    // Openhours
    const fieldOpenHours = document.querySelector('.field-openhours');
    if (fieldOpenHours) {
      const openHoursInputs = fieldOpenHours.querySelectorAll('input');
      fieldOpenHours.addEventListener('click', (event) => {
        form_app.setOpenHoursToLocalStorage(openHoursInputs);
      })
      openHoursInputs.forEach(openHoursInput => {
        openHoursInput.addEventListener('blur', (event) => {
          form_app.setOpenHoursToLocalStorage(openHoursInputs);
        })
      })
    }
    form_app.form.addEventListener('click', (event) => {
      if (!form_app.loading) {
        setTimeout(() => {
          // Taxonomy picker
          const optionValues = document.querySelectorAll('.taxonomy-picker input[name*="options-values"][type="hidden"]');
          let allOptionValues = []
          optionValues.forEach(optionValue => {
            if (optionValue.value) {
              allOptionValues = allOptionValues.concat(JSON.parse(optionValue.value));
            }
          })
          if ((allOptionValues.length) > 0 || localStorage.getItem('element-form-taxonomy')) {
            form_app.setDataToLocalStorage({
              id : 'taxonomy',
              value: JSON.stringify(allOptionValues)
            })
          }
          // Elements field
          const fieldElements =  document.querySelectorAll('.field-elements input.select-encoded-result');
          fieldElements.forEach(fieldElement => {
            if (fieldElement.value && fieldElement.value !== '{}') {
              form_app.setDataToLocalStorage(fieldElement);            
            }
          })
        }, 100)
      }
    })
  },
  
  setOpenHoursToLocalStorage: (openHoursInputs) => {
    setTimeout(() => {
      openHoursInputs.forEach(openHoursInput => {
        if (openHoursInput.type==='time') {
          form_app.setDataToLocalStorage(openHoursInput);
        }
      })
    }, 100);
  },
  
  setDataToLocalStorage: (element) => {
    if (form_app.loading) {
      return;
    }
    localStorage.setItem('element-form-updatedAt', Date.now());
    let id = element.id;
    let value = element.value;
    switch (element.type) {
      case 'checkbox'  : value = element.checked ? 'on' : 'off'; break;
      case 'radio'     : value = id; id = element.name; break;
      case 'select-one': id = element.name; break;
      case 'time'      : id = element.name; break;
      case 'hidden'    : id = element.name; break;
    }
    if (id) {
      localStorage.setItem('element-form-' + id, value);
    }
  },
  
  checkForDataInLocalStorage: () => {
    const updatedAt = localStorage.getItem('element-form-updatedAt');
    if (updatedAt) {
      const delay = Date.now() - updatedAt;
      if (delay > 1000*60*60*24) {
        clear_form_app.clearLocalStorage();
      } else {
        $('#popup-get-data-from-local-storage').openModal();
        const btnOk = document.querySelector('#popup-get-data-from-local-storage .btn-ok');
        btnOk.addEventListener('click', form_app.getDataFromLocalStorage);
      }
    }
  },
  
  getDataFromLocalStorage: (element) => {
    form_app.loading = true;
    const inputs = form_app.form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
      let key = input.id; (input.type !== 'radio') ? input.id : input.name;
      switch (input.type) {
        case 'radio'     : key = input.name; break;
        case 'select-one': key = input.name; break;
        case 'time'      : key = input.name; break;
        case 'hidden'    : key = input.name; break;
      }
      if (key) {
        key = 'element-form-' + key;
      } else {
        return;
      }
      if (localStorage.getItem(key)) {
        switch (input.type) {
          case 'checkbox'  : input.checked = (localStorage.getItem(key) === 'on'); break;
          case 'radio'     : input.checked = (localStorage.getItem(key) === input.id); break;
          case 'select-one':
            const option = input.querySelector('option[value=' + localStorage.getItem(key) + ']');
            option.selected = true;
            const displayedOptions = input.closest('.input-field.select-input').querySelectorAll('.select-input ul.dropdown-content li');
            displayedOptions[input.selectedIndex].click();
            break;
          case 'hidden'    :
            if (!input.classList.contains('select-encoded-result')) {
              return;
            } else {
            // elements field
              input.value = localStorage.getItem(key);
              let elementsFieldValue = input.value.split(':')[1];
              elementsFieldValue = elementsFieldValue.substring(1, elementsFieldValue.length - 2);
              const select2Selection = input.closest('.select-wrapper').querySelector('.select2-selection__rendered');
              select2Selection.innerHTML = elementsFieldValue;
              select2Selection.title = elementsFieldValue;
            }
            break;
          default          : input.value = localStorage.getItem(key);
        }
        // Address
        if (input.id === 'input-address' ) {
          const geolocalizeBtn = input.closest('#input-address-field').querySelector('.btn-geolocalize');
          if (geolocalizeBtn) {
            setTimeout(() => geolocalizeBtn.click(), 100);
          }
        }
        // Date picker
        const isDateField = input.closest('.field-container.field-date');
        if (isDateField) {
            input.dispatchEvent(new KeyboardEvent('keyup'));
            document.querySelectorAll('.daterangepicker').forEach( dateRangePicker => {
              if (dateRangePicker.style.display !== 'none') {
                dateRangePicker.querySelector('button.applyBtn').click();
              }
            })
        }
        // OpenHours second slot
        if (key.includes('[slot2')) {
          const addTimeSlotButton = input.closest('.open-hours-container').querySelector('.add-time-slot-button');
          setTimeout(() => { addTimeSlotButton.click(); }, 100);
        }
        // Label
        if (input.type !== 'time') {
          setTimeout(() => { input.focus() }, 200);
        }
      }
      // Scroll to top and close popups
      setTimeout(() => { 
        document.body.scrollTop = document.documentElement.scrollTop = 0;
        document.body.click();
      }, 300)
      form_app.loading = false;
    })
    // Taxonomy picker
    window.apps.forEach(app => {
      if (app.$refs.taxonomyPicker) {
        if (localStorage.getItem('element-form-taxonomy')) {
          app.$refs.taxonomyPicker.resetValues(JSON.parse(localStorage.getItem('element-form-taxonomy')))
        }
      }
    })
  }
  
}
document.addEventListener('DOMContentLoaded', form_app.init);


