<?php

/**
 * @file classes/SwordSchemaMigration.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SwordSchemaMigration
 * @brief Describe database table structures.
 */

namespace APP\plugins\generic\sword\classes;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SwordSchemaMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Deposit points.
		Schema::create('deposit_points', function (Blueprint $table) {
			$table->bigInteger('deposit_point_id')->autoIncrement();
			$table->bigInteger('context_id');
			$table->string('url', 2047);
			$table->float('seq', 8, 2)->default(0);
			$table->tinyInteger('type')->default(0);
			$table->string('sword_username', 2047);
			$table->string('sword_password', 2047);
			$table->string('sword_apikey', 2047);
			$table->index(['context_id'], 'deposit_points_context_id');
		});

		// Locale-specific deposit point data
		Schema::create('deposit_point_settings', function (Blueprint $table) {
			$table->bigInteger('deposit_point_id');
			$table->string('locale', 5)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->string('setting_type', 6);
			$table->index(['deposit_point_id'], 'deposit_point_settings_deposit_point_id');
			$table->unique(['deposit_point_id', 'locale', 'setting_name'], 'deposit_point_settings_pkey');
		});

	}
}
