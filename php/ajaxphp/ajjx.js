/**
 * Created by donastephen on 9/10/15.
 */
$('document').ready( function(){
    var data = $(this).serialize();
    $.ajax({
            type:"POST",
            dataType: "json",
            url:"response.php",
            data:data,
            success: function(data){
                $('.the-return').html(

                );
            },
            error: function(xhr, desc, err){

            }

        }

    )

});