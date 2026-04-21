window.addEventListener('load', function () {
  console.log('SHED CROPPER LOADED');

  var featuredInput = document.querySelector('input[name="upload-2"], input[name="upload-2[]"]');
  var hiddenInput = document.querySelector('textarea[name="textarea-2"], textarea[name="textarea-2[]"]');
  var form = document.querySelector('form.forminator-custom-form');

  var modal = document.getElementById('shed-cropper-modal');
  var cropperImage = document.getElementById('shed-cropper-image');
  var applyBtn = document.getElementById('shed-cropper-apply');
  var cancelBtn = document.getElementById('shed-cropper-cancel');

  console.log('featuredInput:', featuredInput);
  console.log('hiddenInput:', hiddenInput);
  console.log('form:', form);
  console.log('modal:', modal);

  if (!featuredInput || !hiddenInput || !form || !modal || !cropperImage || !applyBtn || !cancelBtn) {
    console.log('SHED CROPPER: required elements missing');
    return;
  }

  var cropper = null;
  var objectUrl = null;
  var cropConfirmed = false;

  function openModal() {
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.style.display = 'none';
    document.body.style.overflow = '';
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

  featuredInput.addEventListener('change', function (e) {
    console.log('SHED CROPPER: featured image changed');

    cropConfirmed = false;
    hiddenInput.value = '';

    var file = e.target.files && e.target.files[0];
    console.log('SHED CROPPER: selected file =', file);

    if (!file) {
      return;
    }

    if (!file.type.match(/^image\//)) {
      alert('Please choose an image file.');
      featuredInput.value = '';
      return;
    }

    destroyCropper();

    objectUrl = URL.createObjectURL(file);
    console.log('SHED CROPPER: objectUrl =', objectUrl);

    cropperImage.src = objectUrl;
    openModal();

    cropperImage.onload = function () {
      console.log('SHED CROPPER: image loaded into modal');

      if (cropper) {
        cropper.destroy();
      }

      cropper = new Cropper(cropperImage, {
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

      console.log('SHED CROPPER: cropper initialised');
    };
  });

  applyBtn.addEventListener('click', function () {
    if (!cropper) {
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

    hiddenInput.value = canvas.toDataURL('image/jpeg', 0.9);
    cropConfirmed = true;
    closeModal();
    console.log('SHED CROPPER: crop applied');
  });

  cancelBtn.addEventListener('click', function () {
    cropConfirmed = false;
    hiddenInput.value = '';
    featuredInput.value = '';
    destroyCropper();
    closeModal();
    console.log('SHED CROPPER: crop cancelled');
  });

  form.addEventListener('submit', function (e) {
    if (featuredInput.files.length > 0 && !cropConfirmed) {
      e.preventDefault();
      alert('Please crop the featured image before submitting.');
      openModal();
      console.log('SHED CROPPER: submit blocked until crop applied');
    }
  });
});