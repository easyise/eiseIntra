$(document).ready(function(){

    $('body').eiseIntra();

});

$(window).on('load', function(){

    $('body').eiseIntra('doVisualAdjustments')
        .eiseIntra('showMessage');


})