
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAddressesTable extends Migration
{
    public function up()
    {
       $tableNames = config('address.tables');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/address.php not loaded. Run [php artisan config:clear] and try again.');
        }

        Schema::create($tableNames['addresses'], function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->nullableMorphs('addressable');
            $table->string('label', 60)->nullable();
            $table->string('given_name', 60)->nullable();
            $table->string('family_name', 60)->nullable();
            $table->string('organization', 100)->nullable();
            $table->string('street', 60)->nullable();
            $table->string('street_extra', 60)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('province', 60)->nullable();
            $table->string('post_code', 10)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->jsonb('extra')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            foreach(config('address.flags', (new (config('address.models.address')))::FLAGS) as $flag) {
                $table->boolean('is_'. $flag)->default(false)->index();
            }

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {

         $tableNames = config('address.tables');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/address.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::dropIfExists($tableNames['addresses']);
    }
}
