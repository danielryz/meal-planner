<?php

declare(strict_types=1);

namespace App\Database;

use App\Repositories\RecipeRepository;
use App\Repositories\UserRepository;
use PDO;

final class DataSeeder
{
    public function __construct(
        private readonly PDO $connection,
        private readonly UserRepository $users,
        private readonly RecipeRepository $recipes,
    ) {
    }

    public function run(): void
    {
        $this->seedReferenceData();
        $this->seedDemoUsers();
        $this->seedDemoRecipes();
    }

    private function seedReferenceData(): void
    {
        $this->upsert('roles', 'name', 'label', [
            ['name' => 'admin',    'label' => 'Admin'],
            ['name' => 'owner',    'label' => 'Właściciel'],
            ['name' => 'employee', 'label' => 'Pracownik'],
            ['name' => 'user',     'label' => 'Użytkownik'],
        ]);

        $this->upsert('diet_types', 'code', 'label', [
            ['code' => 'standard',    'label' => 'Standardowa'],
            ['code' => 'vegetarian',  'label' => 'Wegetariańska'],
            ['code' => 'vegan',       'label' => 'Wegańska'],
            ['code' => 'gluten_free', 'label' => 'Bez glutenu'],
            ['code' => 'keto',        'label' => 'Keto'],
        ]);

        $this->upsert('allergy_types', 'code', 'label', [
            ['code' => 'nuts',   'label' => 'Orzechy'],
            ['code' => 'gluten', 'label' => 'Gluten'],
            ['code' => 'dairy',  'label' => 'Nabiał'],
            ['code' => 'eggs',   'label' => 'Jajka'],
        ]);

        $this->upsert('cuisine_types', 'code', 'label', [
            ['code' => 'polish',         'label' => 'Polska'],
            ['code' => 'italian',        'label' => 'Włoska'],
            ['code' => 'asian',          'label' => 'Azjatycka'],
            ['code' => 'mexican',        'label' => 'Meksykańska'],
            ['code' => 'mediterranean',  'label' => 'Śródziemnomorska'],
        ]);

        $this->upsert('recipe_categories', 'code', 'label', [
            ['code' => 'breakfast', 'label' => 'Śniadanie', 'sort_order' => 10],
            ['code' => 'lunch',     'label' => 'Lunch',     'sort_order' => 20],
            ['code' => 'dinner',    'label' => 'Obiad',     'sort_order' => 30],
            ['code' => 'supper',    'label' => 'Kolacja',   'sort_order' => 40],
            ['code' => 'soup',      'label' => 'Zupa',      'sort_order' => 50],
            ['code' => 'dessert',   'label' => 'Deser',     'sort_order' => 60],
            ['code' => 'snack',     'label' => 'Przekąska', 'sort_order' => 70],
        ]);

        $this->upsert('recipe_tags', 'code', 'label', [
            ['code' => 'quick',     'label' => 'Szybkie'],
            ['code' => 'budget',    'label' => 'Budżetowe'],
            ['code' => 'family',    'label' => 'Rodzinne'],
            ['code' => 'protein',   'label' => 'Białkowe'],
            ['code' => 'seasonal',  'label' => 'Sezonowe'],
            ['code' => 'meal_prep', 'label' => 'Meal prep'],
        ]);

        $this->upsert('grocery_item_categories', 'code', 'label', [
            ['code' => 'vegetables', 'label' => 'Warzywa',          'sort_order' => 10],
            ['code' => 'fruit',      'label' => 'Owoce',            'sort_order' => 20],
            ['code' => 'meat_fish',  'label' => 'Mieso i ryby',     'sort_order' => 30],
            ['code' => 'dairy',      'label' => 'Nabial',           'sort_order' => 40],
            ['code' => 'grains',     'label' => 'Produkty sypkie',  'sort_order' => 50],
            ['code' => 'spices',     'label' => 'Przyprawy',        'sort_order' => 60],
            ['code' => 'other',      'label' => 'Inne',             'sort_order' => 100],
        ]);

        echo "Reference data seeded.\n";
    }

    private function seedDemoUsers(): void
    {
        // password: "password"
        $hash = password_hash('password', PASSWORD_BCRYPT);

        if (!$this->users->emailExists('owner@example.com')) {
            $this->users->createUserWithRole('owner', 'owner@example.com', 'owner_demo', $hash, 'Wlasciciel MealPlanner');
            echo "Created user owner@example.com\n";
        }

        if (!$this->users->emailExists('employee@example.com')) {
            $this->users->createUserWithRole('employee', 'employee@example.com', 'employee_demo', $hash, 'Pracownik MealPlanner');
            echo "Created user employee@example.com\n";
        }

        if (!$this->users->emailExists('user@example.com')) {
            $this->users->createUserWithRole('user', 'user@example.com', 'user_demo', $hash, 'Uzytkownik Demo');
            echo "Created user user@example.com\n";
        }
    }

    private function seedDemoRecipes(): void
    {
        if ($this->recipes->slugExists('makaron-z-warzywami')) {
            echo "Recipes already seeded. Nothing to do.\n";
            return;
        }

        $regularUser = $this->users->findAuthUserByEmail('user@example.com');
        $owner       = $this->users->findAuthUserByEmail('owner@example.com');

        if ($regularUser === null || $owner === null) {
            throw new \RuntimeException('Demo users not found.');
        }

        $userId  = $regularUser->id();
        $ownerId = $owner->id();

        $id0 = $this->recipes->createRecipe($userId, [
            'categoryCode'    => 'dinner',
            'title'           => 'Makaron z warzywami',
            'slug'            => 'makaron-z-warzywami',
            'description'     => 'Prosty obiad demo z makaronem, sezonowymi warzywami i lekkim sosem.',
            'difficulty'      => 'easy',
            'prepTimeMinutes' => 25,
            'servings'        => 2,
            'status'          => 'approved',
            'visibility'      => 'public',
        ]);
        $this->recipes->addNutrition($id0, ['calories' => 520, 'protein' => 18.00, 'fat' => 15.00, 'carbs' => 72.00, 'fiber' => 9.00]);
        $this->recipes->addIngredient($id0, 1, 'Makaron pelnoziarnisty', '160 g');
        $this->recipes->addIngredient($id0, 2, 'Cukinia', '1 sztuka', 'Pokrojona w polplastry');
        $this->recipes->addIngredient($id0, 3, 'Sos pomidorowy', '200 ml');
        $this->recipes->addStep($id0, 1, 'Ugotuj makaron zgodnie z instrukcja na opakowaniu.');
        $this->recipes->addStep($id0, 2, 'Podsmaz warzywa, dodaj sos pomidorowy i dopraw do smaku.');
        $this->recipes->addStep($id0, 3, 'Polacz makaron z sosem i podaj od razu po przygotowaniu.');
        $this->recipes->addDietType($id0, 'vegetarian');
        $this->recipes->addTag($id0, 'quick');
        $this->recipes->addTag($id0, 'budget');

        $id1 = $this->recipes->createRecipe($userId, [
            'categoryCode'    => 'breakfast',
            'title'           => 'Owsianka z owocami',
            'slug'            => 'owsianka-z-owocami',
            'description'     => 'Szybkie i sycące śniadanie z płatków owsianych, bananów i świeżych owoców sezonowych.',
            'difficulty'      => 'easy',
            'prepTimeMinutes' => 10,
            'servings'        => 1,
            'status'          => 'approved',
            'visibility'      => 'public',
        ]);
        $this->recipes->addNutrition($id1, ['calories' => 380, 'protein' => 12.00, 'fat' => 8.00, 'carbs' => 62.00, 'fiber' => 7.00]);
        $this->recipes->addIngredient($id1, 1, 'Płatki owsiane', '80 g');
        $this->recipes->addIngredient($id1, 2, 'Mleko', '200 ml', 'Roślinne lub krowie');
        $this->recipes->addIngredient($id1, 3, 'Banan', '1 sztuka');
        $this->recipes->addIngredient($id1, 4, 'Miód', '1 łyżka');
        $this->recipes->addStep($id1, 1, 'Zagotuj mleko w garnku.');
        $this->recipes->addStep($id1, 2, 'Wsyp płatki owsiane i gotuj 5 minut mieszając.');
        $this->recipes->addStep($id1, 3, 'Przełóż do miski, udekoruj bananem i polej miodem.');
        $this->recipes->addDietType($id1, 'vegetarian');
        $this->recipes->addTag($id1, 'quick');
        $this->recipes->addTag($id1, 'budget');

        $id2 = $this->recipes->createRecipe($ownerId, [
            'categoryCode'    => 'soup',
            'title'           => 'Zupa krem z pomidorów',
            'slug'            => 'zupa-krem-z-pomidorow',
            'description'     => 'Gładka i aromatyczna zupa krem z dojrzałych pomidorów z bazylią i śmietaną.',
            'difficulty'      => 'easy',
            'prepTimeMinutes' => 35,
            'servings'        => 4,
            'status'          => 'approved',
            'visibility'      => 'public',
        ]);
        $this->recipes->addNutrition($id2, ['calories' => 210, 'protein' => 5.00, 'fat' => 9.00, 'carbs' => 28.00, 'fiber' => 5.00]);
        $this->recipes->addIngredient($id2, 1, 'Pomidory pelati', '800 g', 'Lub świeże dojrzałe');
        $this->recipes->addIngredient($id2, 2, 'Cebula', '1 sztuka');
        $this->recipes->addIngredient($id2, 3, 'Śmietana 18%', '100 ml');
        $this->recipes->addIngredient($id2, 4, 'Bazylia', '1 gałązka', 'Świeża');
        $this->recipes->addStep($id2, 1, 'Podsmaż cebulę na oliwie do zeszklenia.');
        $this->recipes->addStep($id2, 2, 'Dodaj pomidory, gotuj 20 minut, zmiksuj blenderem.');
        $this->recipes->addStep($id2, 3, 'Wlej śmietanę, dopraw solą i pieprzem. Podaj z bazylią.');
        $this->recipes->addDietType($id2, 'vegetarian');

        $id3 = $this->recipes->createRecipe($ownerId, [
            'categoryCode'    => 'dinner',
            'title'           => 'Kurczak pieczony z ziemniakami',
            'slug'            => 'kurczak-pieczony-z-ziemniakami',
            'description'     => 'Klasyczny obiad z chrupiącym kurczakiem, aromatycznymi ziemniakami i rozmarynem.',
            'difficulty'      => 'medium',
            'prepTimeMinutes' => 75,
            'servings'        => 4,
            'status'          => 'approved',
            'visibility'      => 'public',
        ]);
        $this->recipes->addNutrition($id3, ['calories' => 650, 'protein' => 48.00, 'fat' => 28.00, 'carbs' => 45.00, 'fiber' => 4.00]);
        $this->recipes->addIngredient($id3, 1, 'Kurczak', '1,2 kg', 'Cały lub udka');
        $this->recipes->addIngredient($id3, 2, 'Ziemniaki', '800 g');
        $this->recipes->addIngredient($id3, 3, 'Rozmaryn', '2 gałązki');
        $this->recipes->addIngredient($id3, 4, 'Oliwa z oliwek', '3 łyżki');
        $this->recipes->addStep($id3, 1, 'Rozgrzej piekarnik do 200°C.');
        $this->recipes->addStep($id3, 2, 'Natrzyj kurczaka oliwą, solą, pieprzem i rozmarynem.');
        $this->recipes->addStep($id3, 3, 'Obierz ziemniaki, pokrój, ułóż wokół kurczaka.');
        $this->recipes->addStep($id3, 4, 'Piecz 60 minut aż skóra będzie złocista.');
        $this->recipes->addTag($id3, 'family');

        $id4 = $this->recipes->createRecipe($userId, [
            'categoryCode'    => 'lunch',
            'title'           => 'Sałatka z tuńczykiem',
            'slug'            => 'salatka-z-tunczykiem',
            'description'     => 'Lekki lunch z tuńczykiem, mieszanką sałat, pomidorkami cherry i oliwą z oliwek.',
            'difficulty'      => 'easy',
            'prepTimeMinutes' => 15,
            'servings'        => 2,
            'status'          => 'approved',
            'visibility'      => 'public',
        ]);
        $this->recipes->addNutrition($id4, ['calories' => 320, 'protein' => 28.00, 'fat' => 14.00, 'carbs' => 18.00, 'fiber' => 3.00]);
        $this->recipes->addIngredient($id4, 1, 'Tuńczyk w oliwie', '1 puszka (160 g)');
        $this->recipes->addIngredient($id4, 2, 'Mix sałat', '100 g');
        $this->recipes->addIngredient($id4, 3, 'Pomidorki cherry', '150 g');
        $this->recipes->addStep($id4, 1, 'Odsącz tuńczyka z oliwy.');
        $this->recipes->addStep($id4, 2, 'Wymieszaj sałaty, pomidorki i tuńczyka. Skrop oliwą i cytryną.');
        $this->recipes->addDietType($id4, 'gluten_free');
        $this->recipes->addTag($id4, 'quick');
        $this->recipes->addTag($id4, 'protein');

        $id5 = $this->recipes->createRecipe($ownerId, [
            'categoryCode'    => 'dessert',
            'title'           => 'Brownie czekoladowe',
            'slug'            => 'brownie-czekoladowe',
            'description'     => 'Wilgotne i intensywne w smaku brownie z ciemnej czekolady — idealne na deser.',
            'difficulty'      => 'medium',
            'prepTimeMinutes' => 45,
            'servings'        => 8,
            'status'          => 'approved',
            'visibility'      => 'public',
        ]);
        $this->recipes->addNutrition($id5, ['calories' => 480, 'protein' => 7.00, 'fat' => 26.00, 'carbs' => 56.00, 'fiber' => 3.00]);
        $this->recipes->addIngredient($id5, 1, 'Czekolada gorzka', '200 g', 'Min. 70%');
        $this->recipes->addIngredient($id5, 2, 'Masło', '125 g');
        $this->recipes->addIngredient($id5, 3, 'Jajka', '3 sztuki');
        $this->recipes->addIngredient($id5, 4, 'Cukier', '150 g');
        $this->recipes->addIngredient($id5, 5, 'Mąka pszenna', '80 g');
        $this->recipes->addStep($id5, 1, 'Rozpuść czekoladę z masłem w kąpieli wodnej.');
        $this->recipes->addStep($id5, 2, 'Ubij jajka z cukrem, połącz z masą czekoladową.');
        $this->recipes->addStep($id5, 3, 'Dodaj mąkę, wymieszaj, przelej do formy 20x20 cm.');
        $this->recipes->addStep($id5, 4, 'Piecz 25 minut w 180°C. Brownie ma być lekko wilgotne w środku.');

        $this->recipes->createRecipe($userId, [
            'categoryCode'    => 'dinner',
            'title'           => 'Risotto z grzybami',
            'slug'            => 'risotto-z-grzybami',
            'description'     => 'Kremowe risotto z leśnymi grzybami — szkic czeka na publikację.',
            'difficulty'      => 'advanced',
            'prepTimeMinutes' => 50,
            'servings'        => 3,
            'status'          => 'draft',
            'visibility'      => 'private',
        ]);

        $this->recipes->addFavorite($userId, $id2);
        $this->recipes->addFavorite($userId, $id1);
        $this->recipes->addFavorite($userId, $id5);

        echo "Recipes seeded.\n";
    }

    private function upsert(string $table, string $conflictKey, string $labelKey, array $rows): void
    {
        $first      = $rows[0];
        $columns    = array_keys($first);
        $colList    = implode(', ', $columns);
        $placeholders = implode(', ', array_map(fn($c) => ':' . $c, $columns));
        $updateCols = array_filter($columns, fn($c) => $c !== $conflictKey);
        $updateSet  = implode(', ', array_map(fn($c) => "{$c} = EXCLUDED.{$c}", $updateCols));

        $sql  = "INSERT INTO {$table} ({$colList}) VALUES ({$placeholders}) ";
        $sql .= "ON CONFLICT ({$conflictKey}) DO UPDATE SET {$updateSet}";

        $stmt = $this->connection->prepare($sql);

        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }
}
