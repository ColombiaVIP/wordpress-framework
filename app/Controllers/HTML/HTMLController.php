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
        <form method='post' class="cvipTable <?=$class?>" action='<?=$action?>'>
            <?=$fields?> 
        </form>
        <?PHP return ob_get_clean();
    }
    public static function hidden($name, $value){
        ob_start(); ?>
            <input type='hidden' name='<?=$name?>' value='<?=$value?>'>
        <?php return ob_get_clean();
    }

    public static function text(string $name, $value){
        ob_start(); ?>
            <input type='text' name='<?=$name?>' value='<?=$value?>'>
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

    public static function textarea(string $name, string $value){
        ob_start(); ?>
            <textarea name='<?=$name?>'><?=$value?></textarea>
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
                <div class="imageList">
                <?php foreach( explode(",",$field['Value']) as $image) : ?>
                    <div>
                    <img src="<?= $image ?>" alt="" width="50px">
                    <span><?= $image ?></span>
                    </div>
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
                        var relativeUrl = [];
                        wp.media.editor.send.attachment = function(props, attachment) {

                            relativeUrl.push  (attachment.url.replace(websiteName, ""));
                            $('.<?=$key?>').val(relativeUrl.join());
                            // console.log("website:" + websiteName);
                            $('.imageList').text(relativeUrl.join());                        
                        }
                        return false;
                    });

                    $('.remove_<?=$key; ?>').click(function() {
                        // event.preventDefault()
                        var answer = confirm('Seguro?');
                        if (answer == true) {
                        $('.<?=$key?>').val("").text("");
                        $('.imageList').text("");

                        }
                        return false;
                    });

                    });
                </script>
            </div>

        <?php return ob_get_clean();
    }
     
}