document.addEventListener('DOMContentLoaded', function() {
  // Adds a class to the mongo association fields so we can
  // easily change the style

  // small hack to find those kind of components
  $('.field-actions > .btn-group + .modal').each(function() {
    $(this).closest('.sonata-ba-field').addClass('mongo-association-field')
  })
})