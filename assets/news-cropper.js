(function () {
  var cropper = null;
  var objectUrl = null;
  var activeInput = null;
  var activeHiddenInput = null;
  var cropConfirmed = false;
  var loadingCropper = false;

  function qs(selector) {
    return document.querySelector(selector);
  }

  function ensureModal() {
    var modal = qs('#shed-cropper-modal');

    if (modal) {
      return modal;
    }

    modal = document.createElement('div');
    modal.id = 'shed-cropper-modal';
    modal.style.display = 'none';
    modal.innerHTML = [
      '<div id="shed-cropper-backdrop"></div>',
      '<div id="shed-cropper-panel">',
      '<div class="shed-cropper-wrap"><img id="shed-cropper-image" src="" alt="Crop image"></div>',
      '<div class="shed-cropper-buttons">',
      '<button type="button" id="shed-cropper-cancel">Cancel</button>',
      '<button type="button" id="shed-cropper-apply">Apply crop</button>',
      '</div>',
      '</div>'
    ].join('');
    document.body.appendChild(modal);

    return modal;
  }

  function loadCropper(callback) {
    if (typeof window.Cropper !== 'undefined') {
      callback();
      return;
    }

    if (loadingCropper) {
      window.setTimeout(function () {
        loadCropper(callback);
      }, 100);
      return;
    }

    loadingCropper = true;
    var script = document.createElement('script');
    script.src = 'https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js';
    script.onload = function () {
      loadingCropper = false;
      callback();
    };
    script.onerror = function () {
      loadingCropper = false;
      alert('The image cropper could not be loaded. Please check the page network settings.');
    };
    document.head.appendChild(script);
  }

  function findHiddenInput(input) {
    var form = input.closest('form');
    var selector = input.getAttribute('data-shed-cropper-hidden') || '';

    if (form && selector) {
      return form.querySelector(selector);
    }

    if (form) {
      return form.querySelector('input[name="featured_crop_base64"], textarea[name="textarea-2"], textarea[name="textarea-2[]"]');
    }

    return null;
  }

  function isFeaturedInput(input) {
    if (!input || !input.matches || !input.matches('input[type="file"]')) {
      return false;
    }

    if (input.matches('[data-shed-cropper="featured"]')) {
      return true;
    }

    return input.matches('input[name="upload-2"], input[name="upload-2[]"]');
  }

  function destroyCropper() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }

    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }
  }

  function openModal() {
    var modal = ensureModal();
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    var modal = ensureModal();
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  function startCropper(input, file) {
    var image = qs('#shed-cropper-image');

    if (!image) {
      ensureModal();
      image = qs('#shed-cropper-image');
    }

    if (!image || !file) {
      return;
    }

    activeInput = input;
    activeHiddenInput = findHiddenInput(input);
    cropConfirmed = false;

    if (activeHiddenInput) {
      activeHiddenInput.value = '';
    }

    destroyCropper();
    objectUrl = URL.createObjectURL(file);
    image.onload = function () {
      loadCropper(function () {
        if (cropper) {
          cropper.destroy();
        }

        cropper = new window.Cropper(image, {
          aspectRatio: 16 / 9,
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 1,
          responsive: true,
          restore: false,
          guides: true,
          center: true,
          highlight: false,
          cropBoxMovable: true,
          cropBoxResizable: false,
          toggleDragModeOnDblclick: false,
          background: false
        });
      });
    };
    image.src = objectUrl;
    openModal();
  }

  document.addEventListener('change', function (event) {
    var input = event.target;

    if (!isFeaturedInput(input)) {
      return;
    }

    var file = input.files && input.files[0];

    if (!file) {
      return;
    }

    if (!file.type.match(/^image\//)) {
      alert('Please choose an image file.');
      input.value = '';
      return;
    }

    startCropper(input, file);
  }, true);

  document.addEventListener('click', function (event) {
    if (event.target && event.target.id === 'shed-cropper-apply') {
      if (!cropper || !activeHiddenInput) {
        return;
      }

      var canvas = cropper.getCroppedCanvas({
        width: 1600,
        height: 900,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
      });

      if (!canvas) {
        alert('Could not crop the image.');
        return;
      }

      activeHiddenInput.value = canvas.toDataURL('image/jpeg', 0.9);
      cropConfirmed = true;
      closeModal();
    }

    if (event.target && event.target.id === 'shed-cropper-cancel') {
      cropConfirmed = false;

      if (activeHiddenInput) {
        activeHiddenInput.value = '';
      }

      if (activeInput) {
        activeInput.value = '';
      }

      destroyCropper();
      closeModal();
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var input = form && form.querySelector ? form.querySelector('input[type="file"][data-shed-cropper="featured"], input[name="upload-2"], input[name="upload-2[]"]') : null;
    var hidden = input ? findHiddenInput(input) : null;

    if (input && input.files && input.files.length > 0 && hidden && hidden.value === '' && !cropConfirmed) {
      event.preventDefault();
      alert('Please crop the featured image before submitting.');
      startCropper(input, input.files[0]);
    }
  }, true);
}());
