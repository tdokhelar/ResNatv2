// CONFIGURATION ADMIN, disable the whole feature box according to checkbox "feature active"
document.addEventListener('DOMContentLoaded', function() {
    checkCollaborativeVoteActivated();
    $('.collaborative-feature .sonata-ba-field.sonata-ba-field-inline-natural > .form-group:first-child .icheckbox_square-blue .iCheck-helper').click(checkCollaborativeVoteActivated);

    $('.gogo-feature').each(function() {
        checkGoGoFeatureActivated(this);
    });
    $('.gogo-feature .sonata-ba-field.sonata-ba-field-inline-natural > .form-group:first-child .icheckbox_square-blue .iCheck-helper').click(function() {
        var that = this;
        setTimeout(function() { checkGoGoFeatureActivated($(that).closest('.gogo-feature'));  }, 10);
    });
});

function checkCollaborativeVoteActivated() {
    var collabActive = $('.collaborative-feature .sonata-ba-field.sonata-ba-field-inline-natural > .form-group:first-child .icheckbox_square-blue').hasClass('checked');
    var opacity = collabActive ? '1' : '0.4';
    $('.collaborative-moderation-box').css('opacity', opacity);
}

function checkGoGoFeatureActivated(object) {
    var featureActive = $(object).find('.sonata-ba-field.sonata-ba-field-inline-natural > .form-group:first-child .icheckbox_square-blue').hasClass('checked');
    var opacity = featureActive ? '1' : '0.5';
    $(object).css('opacity', opacity);
}

// CONFIGURATION ADMIN, enable element refresh much needed only if element refresh needed mail is activated
document.addEventListener('DOMContentLoaded', function() {
    checkElementRefreshNeededMailActivated();
    $('.refresh-needed-panel .sonata-ba-field > .form-group:first-child .icheckbox_square-blue .iCheck-helper').click(checkElementRefreshNeededMailActivated);
});

function checkElementRefreshNeededMailActivated() {
    var elementRefreshNeededMailActive = $('.refresh-needed-panel .sonata-ba-field > .form-group:first-child .icheckbox_square-blue').hasClass('checked');
    var elementRefreshMuchNeededMailActive = $('.refresh-much-needed-panel .sonata-ba-field > .form-group:first-child .icheckbox_square-blue').hasClass('checked');
    var opacity = elementRefreshNeededMailActive ? '1' : '0.4';
    $('.refresh-much-needed-panel').css('opacity', opacity);
    if (!elementRefreshNeededMailActive && elementRefreshMuchNeededMailActive) {
        $('.refresh-much-needed-panel .sonata-ba-field > .form-group:first-child .icheckbox_square-blue').click();
    }
    var pointerEvents = elementRefreshNeededMailActive ? 'auto' : 'none'; 
    $('.refresh-much-needed-panel').css('pointer-events', pointerEvents);
}