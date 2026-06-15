<?php if ( ! defined( 'ABSPATH' ) ) { exit; } wp_nonce_field( 'wpai_article_type_meta', 'wpai_article_type_nonce' );
$selects = array(
'article_type_tone'=>array('chiaro_didattico_e_operativo','professionale_tecnico','divulgativo_semplice','commerciale_informativo','editoriale_narrativo','personalizzato'),
'article_type_length'=>array('breve','media','lunga','pillar'),
'article_type_search_intent'=>array('informazionale','tutorial','commerciale','transazionale','navigazionale','locale','recensione','comparativo'),
'article_type_reader_level'=>array('principianti','intermedi','avanzati','misto'),
);
$labels = array('description'=>'Descrizione tipologia','goal'=>'Obiettivo articolo','prompt'=>'Prompt principale','structure'=>'Struttura articolo','required_sections'=>'Sezioni obbligatorie','forbidden_patterns'=>'Pattern vietati','preferred_tags'=>'Tag consigliati','internal_linking_rules'=>'Regole link interni','seo_rules'=>'Regole SEO','quality_checklist'=>'Checklist qualità'); ?>
<table class="form-table" role="presentation"><tbody>
<?php foreach ( $labels as $key=>$label ) : $name='article_type_'.$key; ?><tr><th><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html__($label,'wp-ai-publisher'); ?></label></th><td><textarea id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" rows="<?php echo 'prompt'===$key?8:4; ?>" class="large-text"><?php echo esc_textarea($meta[$key] ?? ''); ?></textarea></td></tr><?php endforeach; ?>
<?php foreach ( $selects as $name=>$opts ) : $key=str_replace('article_type_','',$name); ?><tr><th><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html( ucwords(str_replace('_',' ',$key)) ); ?></label></th><td><select id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>"><?php foreach($opts as $opt): ?><option value="<?php echo esc_attr($opt); ?>" <?php selected($meta[$key] ?? '', $opt); ?>><?php echo esc_html($opt); ?></option><?php endforeach; ?></select></td></tr><?php endforeach; ?>
<tr><th><?php echo esc_html__('Categorie consentite','wp-ai-publisher'); ?></th><td><?php foreach ( $categories as $cat ) : ?><label style="display:block"><input type="checkbox" name="article_type_allowed_category_ids[]" value="<?php echo esc_attr($cat->term_id); ?>" <?php checked( in_array( (int) $cat->term_id, (array) $meta['allowed_category_ids'], true ) ); ?> /> <?php echo esc_html($cat->name); ?></label><?php endforeach; ?></td></tr>
<tr><th><?php echo esc_html__('Attiva','wp-ai-publisher'); ?></th><td><label><input type="checkbox" name="article_type_active" value="1" <?php checked( ! empty( $meta['active'] ) ); ?> /> <?php echo esc_html__('Usa questa tipologia nella creazione idee','wp-ai-publisher'); ?></label></td></tr>
</tbody></table>
