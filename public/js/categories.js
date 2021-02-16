'use strict';

$(document).ready(function () {
    checkDocumentVisibility(checkLogin);//check document visibility in order to confirm user's log in status


    //load all category once the page is ready
    //function header: laad_(url)
    laad_();

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    //reload the list of category when fields are changed
    $("#categoriesListSortBy, #categoriesListPerPage").change(function () {
        displayFlashMsg("Please wait...", spinnerClass, "", "");
        laad_();
    });

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    //load and show page when pagination link is clicked
    $("#allcategories").on('click', '.lnp', function (e) {
        e.preventDefault();

        displayFlashMsg("Patientez svp ...", spinnerClass, "", "");

        laad_($(this).attr('href'));

        return false;
    });

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    //handles the addition of new category details .i.e. when "add category" button is clicked
    $("#addCategory").click(function (e) {
        e.preventDefault();

        //reset all error msgs in case they are set
        changeInnerHTML(['nomErr', 'descriptionErr'], "");

        var nom = $("#nom").val();
        var description = $("#description").val();

        //ensure all required fields are filled
        if (!nom) {
            !nom ? changeInnerHTML('nomErr', "Champ obligatoire") : "";

            return;
        }

        //display message telling user action is being processed
        $("#fMsgIcon").attr('class', spinnerClass);
        $("#fMsg").text(" En cours...");

        //make ajax request if all is well
        $.ajax({
            method: "POST",
            url: appRoot + "category/add",
            data: {
                nom: nom,
                description: description
            }
        }).done(function (returnedData) {
            $("#fMsgIcon").removeClass();//remove spinner

            if (returnedData.status === 1) {
                $("#fMsg").css('color', 'green').text(returnedData.msg);

                //reset the form
                document.getElementById("addNewCategoryForm").reset();

                //close the modal
                setTimeout(function () {
                    $("#fMsg").text("");
                    $("#addNewCategoryModal").modal('hide');
                }, 1000);

                //reset all error msgs in case they are set
                changeInnerHTML(['nomErr', 'descriptionErr'], "");

                //refresh category list table
                laad_();

            } else {
                //display error message returned
                $("#fMsg").css('color', 'red').html(returnedData.msg);

                //display individual error messages if applied
                $("#nomErr").text(returnedData.nom);
                $("#descriptionErr").text(returnedData.description);
            }
        }).fail(function () {
            if (!navigator.onLine) {
                $("#fMsg").css('color', 'red').text("Erreur du réseau ! Vérifiez votre connexion svp");
            }
        });
    });


    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    //handles the updating of category details
    $("#editCategorySubmit").click(function (e) {
        e.preventDefault();

        if (formChanges("editCategoryForm")) {
            //reset all error msgs in case they are set
            changeInnerHTML(['nomEditErr', 'descriptionEditErr'], "");

            let nom = $("#nomEdit").val();
            let description = $("#descriptionEdit").val();
            let categoryId = $("#categoryId").val();

            //ensure all required fields are filled
            if (!nom) {
                !nom ? changeInnerHTML('nomEditErr', "Champ obligatoire") : "";

                return;
            }

            if (!categoryId) {
                $("#fMsgEdit").text("Une erreur inattendue s'est produite lors de la modification des détails de la catégorie");
                return;
            }

            //display message telling user action is being processed
            $("#fMsgEditIcon").attr('class', spinnerClass);
            $("#fMsgEdit").text(" Mise à jour des détails...");

            //make ajax request if all is well
            $.ajax({
                method: "POST",
                url: appRoot + "category/update",
                data: {
                    nomEdit: nom,
                    descriptionEdit: description,
                    categoryId: categoryId
                }
            }).done(function (returnedData) {
                $("#fMsgEditIcon").removeClass();//remove spinner

                if (returnedData.status === 1) {
                    $("#fMsgEdit").css('color', 'green').text(returnedData.msg);

                    //reset the form and close the modal
                    setTimeout(function () {
                        $("#fMsgEdit").text("");
                        $("#editCategoryModal").modal('hide');
                    }, 1000);

                    //reset all error msgs in case they are set
                    changeInnerHTML(['nomEditErr', 'descriptionEditErr'], "");

                    //refresh category list table
                    laad_();

                } else {
                    //display error message returned
                    $("#fMsgEdit").css('color', 'red').html(returnedData.msg);

                    //display individual error messages if applied
                    $("#nomEditErr").html(returnedData.nom);
                    $("#descriptionEditErr").html(returnedData.description);
                }
            }).fail(function () {
                if (!navigator.onLine) {
                    $("#fMsgEdit").css('color', 'red').html("Network error! Pls check your network connection");
                }
            });
        } else {
            $("#fMsgEdit").html("Aucun changement a été opéré");
        }
    });

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    //handles category search
    $("#categorySearch").on('keyup change', function () {
        let value = $(this).val();

        if (value) {//search only if there is at least one char in input
            $.ajax({
                type: "get",
                url: appRoot + "search/categorysearch",
                data: {v: value},
                success: function (returnedData) {
                    $("#allcategories").html(returnedData.categoriesTable);
                }
            });
        } else {
            laad_();
        }
    });


    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    //When the trash icon in front of an admin account is clicked on the admin list table (i.e. to delete the account)
    $("#allcategories").on('click', '.deletecategory', function () {
        let confirm = window.confirm("Êtes-vous sûre de vouloir effacer cette Catégorie? C'est une action irreversible!");

        if (confirm) {
            let ElemId = $(this).attr('id');

            let categoryId = ElemId.split("-")[1];//get the categoryId

            //show spinner
            $("#" + ElemId).html("<i class='" + spinnerClass + "'</i>");

            if (categoryId) {
                $.ajax({
                    url: appRoot + "category/delete",
                    method: "POST",
                    data: {_aId: categoryId}
                }).done(function (returnedData) {
                    if (returnedData.status === 1) {
                        changeFlashMsgContent('Catégorie effacée', '', 'green', 1000);
                    } else {
                        alert(returnedData.status);
                    }
                }).fail(function(){
                    console.log('Réquête échouée');
                });
            }
        }
    });


    /*
    ******************************************************************************************************************************
    ******************************************************************************************************************************
    ******************************************************************************************************************************
    ******************************************************************************************************************************
    ******************************************************************************************************************************
    */


    //to launch the modal to allow for the editing of admin info
    $("#allcategories").on('click', '.editcategory', function () {

        let categoryId = $(this).attr('id').split("-")[1];

        $("#categoryId").val(categoryId);

        //get info of admin with categoryId and prefill the form with it
        //alert($(this).siblings(".adminEmail").children('a').html());
        let nom = $(this).siblings(".categoryNom").html();
        let description = $(this).siblings(".categoryDescription").html();

        //prefill the form fields
        $("#nomEdit").val(nom);
        $("#descriptionEdit").val(description);

        $("#editCategoryModal").modal('show');
    });

});


/*
***************************************************************************************************************************************
***************************************************************************************************************************************
***************************************************************************************************************************************
***************************************************************************************************************************************
***************************************************************************************************************************************
*/

/**
 * laad_ = "Load all administrators"
 * @returns {undefined}
 */
function laad_(url) {
    let orderBy = $("#categoriesListSortBy").val().split("-")[0];
    let orderFormat = $("#categoriesListSortBy").val().split("-")[1];
    let limit = $("#categoriesListPerPage").val();

    $.ajax({
        type: 'get',
        url: url ? url : appRoot + "category/laad_/",
        data: {orderBy: orderBy, orderFormat: orderFormat, limit: limit},
    }).done(function (returnedData) {
        hideFlashMsg();

        $("#allcategories").html(returnedData.categoriesTable);
    });
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
