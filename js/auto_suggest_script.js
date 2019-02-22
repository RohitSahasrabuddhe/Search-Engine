// Author: Rohit Sahasrabuddhe
// Date: 11/24/2018
// USC ID: 6377842822

// Javascript code to call auto suggest php service

$(document).ready(function () {

    $("#autocomplete").autocomplete({
        serviceUrl: "autosuggest.php"
    });
});
