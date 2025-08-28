<?php
if (!defined('ABSPATH')) exit;

/**
 * Dwukierunkowa synchronizacja JEDNEGO pola ACF Relationship:
 *  - Pole: accessory_unit_ids (na CPT 'lokale')
 *  - Po zapisaniu lokalu A:
 *      * upewnij się, że każdy wskazany lokal B ma w swoim accessory_unit_ids także A
 *      * usuń A z accessory_unit_ids każdego lokalu B, który został odlinkowany
 *
 * Uwaga: To tworzy relację zwrotną A<->B w tym samym polu.
 */

const DGE_REL_FIELD = 'accessory_unit_ids';

add_action('acf/save_post', function ($post_id) {
    static $IN_SYNC = false;
    if ($IN_SYNC) return;

    // tylko zwykłe posty, bez revisions/autosave
    if (!is_numeric($post_id)) return;
    $post_id = (int)$post_id;
    if (wp_is_post_revision($post_id)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // tylko CPT 'lokale'
    if (get_post_type($post_id) !== 'lokale') return;

    // bieżąca lista wskazań na edytowanym poście
    $current = dge_rel_get($post_id);
    // lista postów, które aktualnie wskazują NA ten post w tym samym polu
    $existing_reverse = dge_rel_find_posts_linking_me($post_id);

    $to_add    = array_diff($current, $existing_reverse);
    $to_remove = array_diff($existing_reverse, $current);

    if (empty($to_add) && empty($to_remove)) return;

    $IN_SYNC = true;

    // DODAJ: dopisz $post_id do accessory_unit_ids na każdym $other z $to_add
    foreach ($to_add as $other_id) {
        if (get_post_type($other_id) !== 'lokale') continue;

        $their = dge_rel_get($other_id);
        if (!in_array($post_id, $their, true)) {
            $their[] = $post_id;
            dge_rel_update($other_id, $their);
        }
    }

    // USUŃ: usuń $post_id z accessory_unit_ids na każdym $other z $to_remove
    foreach ($to_remove as $other_id) {
        if (get_post_type($other_id) !== 'lokale') continue;

        $their = dge_rel_get($other_id);
        $new   = array_values(array_diff($their, [$post_id]));
        if (count($new) !== count($their)) {
            dge_rel_update($other_id, $new);
        }
    }

    $IN_SYNC = false;
}, 20);

/** Pobierz wartości pola relationship jako tablicę intów (surowe, bez formatowania). */
function dge_rel_get(int $post_id): array
{
    if (function_exists('get_field')) {
        $v = get_field(DGE_REL_FIELD, $post_id, false); // false = raw IDs
    } else {
        $v = get_post_meta($post_id, DGE_REL_FIELD, true);
    }
    if (!is_array($v)) $v = (array)$v;
    $v = array_map('intval', array_filter($v));
    // Usuń self-link, żeby nie robić pętli do samego siebie
    $v = array_values(array_diff($v, [$post_id]));
    return $v;
}

/** Zapisz pole relationship (jako array ID). */
function dge_rel_update(int $post_id, array $ids): void
{
    // Unikalność i porządek
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (function_exists('update_field')) {
        update_field(DGE_REL_FIELD, $ids, $post_id);
    } else {
        update_post_meta($post_id, DGE_REL_FIELD, $ids);
    }
}

/**
 * Znajdź posty 'lokale', które w tym samym polu relationship zawierają $post_id.
 * ACF zapisuje to jako zserializowaną tablicę → używamy LIKE na meta.
 */
function dge_rel_find_posts_linking_me(int $post_id): array
{
    $q = new WP_Query([
        'post_type'      => 'lokale',
        'post_status'    => 'any',
        'fields'         => 'ids',
        'nopaging'       => true,
        'meta_query'     => [[
            'key'     => DGE_REL_FIELD,
            'value'   => '"' . $post_id . '"', // element w serialized array
            'compare' => 'LIKE',
        ]],
        'no_found_rows'  => true,
    ]);
    if (is_wp_error($q)) return [];
    // Nie zwracaj samego siebie
    return array_values(array_diff($q->posts ?: [], [$post_id]));
}