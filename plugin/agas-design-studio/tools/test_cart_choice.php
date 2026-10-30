<?php
/** Mock regression for explicit replace/add cart choices. No production data. */
require __DIR__ . '/test_cart_regression.php';

check(AGAS_Design_Studio::cart_choice_action('auto', true) === 'replace', 'legacy cart edit still replaces matching saved design');
check(AGAS_Design_Studio::cart_choice_action('auto', false) === 'add', 'new gallery design still adds cart item');
check(AGAS_Design_Studio::cart_choice_action('replace', true) === 'replace', 'replace explicitly updates existing line');
check(AGAS_Design_Studio::cart_choice_action('add', false) === 'add', 'new independent design adds a separate line');
check(is_wp_error(AGAS_Design_Studio::cart_choice_action('replace', false)), 'cannot replace missing original cart item');
check(is_wp_error(AGAS_Design_Studio::cart_choice_action('add', true)), 'cannot add same saved design id as independent copy');
check(is_wp_error(AGAS_Design_Studio::cart_choice_action('invalid', true)), 'invalid cart modes rejected');
echo "CART_CHOICE_TESTS=PASS\n";
