<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCondition;
use App\Models\ProductMaterial;
use App\Models\ProductParcelSize;

class ProductMetaSeeder extends Seeder
{
    public function run()
    {
        ProductCondition::insert([
            ['key' => 'new_with_tags', 'label' => 'New with Tags', 'description' => 'Brand new, never worn, original tags still attached.'],
            ['key' => 'new_without_tags', 'label' => 'New without Tags', 'description' => 'Brand new and never worn, but no tags.'],
            ['key' => 'like_new', 'label' => 'Like New', 'description' => 'Worn once or twice, no signs of wear.'],
            ['key' => 'excellent', 'label' => 'Excellent Condition', 'description' => 'Very lightly worn, no flaws or damage.'],
            ['key' => 'good', 'label' => 'Good Condition', 'description' => 'Gently used, may show light wear (e.g., minor fading).'],
            ['key' => 'fair', 'label' => 'Fair Condition', 'description' => 'Clearly used, visible wear or small flaws, still wearable.'],
            ['key' => 'vintage', 'label' => 'Vintage / Pre-loved', 'description' => 'Older item with character, may show signs of age.'],
            ['key' => 'repair', 'label' => 'For Parts / Repair', 'description' => 'Damaged, stained, or needs fixing — sold as is.'],
        ]);

        ProductMaterial::insert([
            ['key' => 'acrylic', 'label' => 'Acrylic'],
            ['key' => 'alpaca', 'label' => 'Alpaca'],
            ['key' => 'bamboo', 'label' => 'Bamboo'],
            ['key' => 'canvas', 'label' => 'Canvas'],
            ['key' => 'cardboard', 'label' => 'Cardboard'],
            ['key' => 'cashmere', 'label' => 'Cashmere'],
            ['key' => 'ceramic', 'label' => 'Ceramic'],
            ['key' => 'chiffon', 'label' => 'Chiffon'],
            ['key' => 'corduroy', 'label' => 'Corduroy'],
            ['key' => 'cotton', 'label' => 'Cotton'],
            ['key' => 'denim', 'label' => 'Denim'],
            ['key' => 'down', 'label' => 'Down'],
            ['key' => 'elastane', 'label' => 'Elastane'],
            ['key' => 'faux_fur', 'label' => 'Faux Fur'],
            ['key' => 'faux_leather', 'label' => 'Faux Leather'],
            ['key' => 'felt', 'label' => 'Felt'],
            ['key' => 'flannel', 'label' => 'Flannel'],
            ['key' => 'fleece', 'label' => 'Fleece'],
            ['key' => 'foam', 'label' => 'Foam'],
            ['key' => 'glass', 'label' => 'Glass'],
            ['key' => 'gold', 'label' => 'Gold'],
            ['key' => 'jute', 'label' => 'Jute'],
        ]);

        ProductParcelSize::insert([
            [
                'name' => 'Small Parcel',
                'description' => 'Fits in a padded envelope or small box. Ideal for T-shirts, tops, accessories, or lightweight items.',
            ],
            [
                'name' => 'Medium Parcel',
                'description' => 'Fits in a medium box. Great for jeans, shoes, hoodies, or multiple small items.',
            ],
            [
                'name' => 'Large Parcel',
                'description' => 'Fits in a large box. Suitable for coats, boots, or bulkier clothing pieces.',
            ],
            [
                'name' => 'Extra Large Parcel',
                'description' => 'For oversized or heavy items like winter jackets, bags, or multiple bundled items.',
            ],
        ]);
    }
}
