<?php
/**
 * Phase 5 — StarRatingInputTest
 *
 * Verifies the star_rating_input partial renders correctly:
 *   - 5 radio inputs with values 1..5 and visually-hidden class
 *   - 5 label siblings with Bootstrap Icons + aria-label "N of 5"
 *   - Visually-hidden legend with "Rating" text
 *   - Clear link with data-action="clear"
 *   - data-component="star-rating-input" for the JS handler
 *   - current_value pre-selects the matching radio (checked attr)
 *   - unique_id appears in all input/label id pairs
 *
 * The test renders the partial directly via ob_start/require, matching
 * the ModalRenderTest pattern from Phase 3.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase05\Review;

use PHPUnit\Framework\TestCase;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../../..'));
}

class StarRatingInputTest extends TestCase
{
    public function test_renders_5_radio_inputs_with_values_1_through_5(): void
    {
        $out = $this->renderPartial(['name' => 'rating', 'current_value' => 0, 'unique_id' => 't1']);
        for ($i = 1; $i <= 5; $i++) {
            $this->assertStringContainsString('value="' . $i . '"', $out, "missing radio value={$i}");
            $this->assertMatchesRegularExpression(
                '/<input[^>]*type="radio"[^>]*id="rating-' . $i . '-t1"/',
                $out,
                "missing radio id for value={$i}"
            );
        }
    }

    public function test_renders_5_label_siblings_with_bootstrap_icons(): void
    {
        $out = $this->renderPartial(['name' => 'rating', 'current_value' => 0, 'unique_id' => 't1']);
        // Fieldset with flex row-reverse + data-component attribute.
        $this->assertStringContainsString('data-component="star-rating-input"', $out);
        $this->assertStringContainsString('class="star-rating-input"', $out);
        // 5 labels with bi bi-star + aria-label "N of 5".
        $count = preg_match_all('/class="star-rating-input__icon bi bi-star"/', $out);
        $this->assertSame(5, $count, 'expected 5 star-rating-input__icon labels');
        for ($i = 1; $i <= 5; $i++) {
            $this->assertStringContainsString('aria-label="' . $i . ' of 5"', $out);
        }
    }

    public function test_legend_is_visually_hidden_with_text_rating(): void
    {
        $out = $this->renderPartial(['name' => 'rating', 'current_value' => 0, 'unique_id' => 't1']);
        $this->assertMatchesRegularExpression(
            '/<legend[^>]*class="visually-hidden"[^>]*>Rating<\/legend>/',
            $out
        );
    }

    public function test_clear_link_renders_with_data_action_clear(): void
    {
        $out = $this->renderPartial(['name' => 'rating', 'current_value' => 0, 'unique_id' => 't1']);
        $this->assertStringContainsString('data-action="clear"', $out);
        $this->assertStringContainsString('star-rating-input__clear', $out);
        $this->assertStringContainsString('>Clear</a>', $out);
    }

    public function test_current_value_pre_selects_matching_radio(): void
    {
        $out = $this->renderPartial(['name' => 'rating', 'current_value' => 3, 'unique_id' => 't1']);
        // Radio with value=3 carries checked attribute.
        $this->assertMatchesRegularExpression(
            '/<input[^>]*value="3"[^>]*checked/',
            $out
        );
        // Other radios do NOT carry checked.
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*value="1"[^>]*checked/',
            $out
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*value="5"[^>]*checked/',
            $out
        );
    }

    public function test_unique_id_appears_in_input_label_pairs(): void
    {
        $out = $this->renderPartial(['name' => 'rating', 'current_value' => 0, 'unique_id' => 'modal-42']);
        for ($i = 1; $i <= 5; $i++) {
            $id = "rating-{$i}-modal-42";
            $this->assertStringContainsString('id="' . $id . '"', $out);
            $this->assertStringContainsString('for="' . $id . '"', $out);
        }
    }

    public function test_custom_form_field_name_is_used(): void
    {
        $out = $this->renderPartial(['name' => 'review_score', 'current_value' => 0, 'unique_id' => 't1']);
        $this->assertStringContainsString('name="review_score"', $out);
        // Fieldset also carries name attribute so the form knows the field group.
        $this->assertMatchesRegularExpression(
            '/<fieldset[^>]*name="review_score"/',
            $out
        );
    }

    public function test_radio_inputs_are_visually_hidden_and_required(): void
    {
        $out = $this->renderPartial(['name' => 'rating', 'current_value' => 0, 'unique_id' => 't1']);
        $this->assertMatchesRegularExpression(
            '/<input[^>]*type="radio"[^>]*class="visually-hidden"[^>]*required/',
            $out
        );
    }

    /**
     * Render the partial directly with the given vars.
     */
    private function renderPartial(array $vars): string
    {
        $GLOBALS['_tt_view_vars'] = $vars;
        ob_start();
        try {
            require APP_ROOT . '/src/Support/View/partials/star_rating_input.php';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}