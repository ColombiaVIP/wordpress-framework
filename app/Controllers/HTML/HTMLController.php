<?php
/**
 * 
 * Form Controller.
 * 
 */
namespace WordpressFramework\Controllers\HTML;


class HTMLController {

    public static function form($fields, $class, $action){
        ob_start()?>
        <form method='post' class="cvipForm <?=$class?>" action='<?=$action?>'>
            <?=$fields?> 
        </form>
        <?PHP return ob_get_clean();
    }
    public static function hidden($name, $value){
        ob_start(); ?>
            <input type='hidden' name='<?=$name?>' value='<?=$value?>'>
        <?php return ob_get_clean();
    }

    public static function text(string $name, string $value, ?string $required, ?string $readonly){
        ob_start(); ?>
            <input type='text' name='<?=$name?>' value='<?=$value?>' <?=$required?> <?=$readonly?>>
        <?php return ob_get_clean();
    }
    public static function textarea(string $name, string $value, ?string $required, ?string $readonly){
        ob_start(); ?>
            <textarea name='<?=$name?>' <?=$required?> <?=$readonly?>><?=$value?></textarea>
        <?php return ob_get_clean();
    }

    public static function label(string $for, string $text){
        ob_start(); ?>
            <label><?=$for?>:<?=$text?></label>
        <?php return ob_get_clean();
    }

    public static function submit(string $value){
        ob_start(); ?>
            <input type='submit' value='<?=$value?>'>
        <?php return ob_get_clean();
    }


    public static function select(string $name, array $options, string $selected){
        ob_start(); ?>
            <select name='<?=$name?>'>
                <?php foreach ($options as $option): ?>
                    <option value='<?=$option?>' <?=($option==$selected)?"selected":""?>><?=$option?></option>
                <?php endforeach; ?>
            </select>
        <?php return ob_get_clean();
    }
    public static function media($key, $name, $field, $required) {
        wp_enqueue_media();
        ob_start(); ?>
            <div class="mediaUpload">
                <input type="hidden" class="myfile <?=$key?>" name="<?=$name?>" value="<?=$field['Value']?>" placeholder="UPLOAD YOUR IMAGE" <?=$required?> >
                <div class="imageList<?=$key?>">
                <?php foreach( explode(",",(string)$field['Value']) as $image) : ?>
                    <img src="<?= $image ?>" width="100px">
                <?php endforeach; ?>
                </div>                    
                    
                

                <button class="upload upload_<?=$key?>">SUBIR</button>
                <button class="remove remove_<?=$key?>">&times;</button>

                <script type="text/javascript" style="display:none">
                    jQuery(document).ready(function($) {

                    // The "Upload" button
                    $('.upload_<?=$key; ?>').click(function(e) {
                        // e.preventDefault()

                        wp.media.editor.open(button);
                        var button = $(this);
                        var websiteName = window.location.protocol+"//"+window.location.host;
                        $('.imageList<?=$key?>').text("");
                        var relativeUrls = [];
                        wp.media.editor.send.attachment = function(props, attachment) {
                            var relativeUrl=attachment.url.replace(websiteName, "");
                            $('.imageList<?=$key?>').append(
                                    "<img src='"+relativeUrl+"' width='100px'>"
                            );                        
                            relativeUrls.push  (relativeUrl);
                            $('.<?=$key?>').val(relativeUrls.join());
                        }
                        return false;
                    });

                    $('.remove_<?=$key; ?>').click(function() {
                        // event.preventDefault()
                        var answer = confirm('Seguro?');
                        if (answer == true) {
                        $('.<?=$key?>').val("").text("");
                        $('.imageList<?=$key?>').text("");

                        }
                        return false;
                    });

                    });
                </script>
            </div>

        <?php return ob_get_clean();
    }
     
}