<?php

namespace pragmatic\analytics\migrations;

use craft\db\Migration;

class m260212_000001_create_analytics_tables extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%pragmaticanalytics_daily_stats}}', [
            'date' => $this->date()->notNull(),
            'visits' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'uniqueVisitors' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'PRIMARY KEY([[date]])',
        ]);

        $this->createTable('{{%pragmaticanalytics_page_daily_stats}}', [
            'date' => $this->date()->notNull(),
            'path' => $this->string(1024)->notNull(),
            'visits' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'PRIMARY KEY([[date]], [[path]])',
        ]);

        $this->createTable('{{%pragmaticanalytics_daily_unique_visitors}}', [
            'date' => $this->date()->notNull(),
            'visitorHash' => $this->char(64)->notNull(),
            'PRIMARY KEY([[date]], [[visitorHash]])',
        ]);

        $this->createIndex(
            null,
            '{{%pragmaticanalytics_page_daily_stats}}',
            ['path'],
            false
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%pragmaticanalytics_daily_unique_visitors}}');
        $this->dropTableIfExists('{{%pragmaticanalytics_page_daily_stats}}');
        $this->dropTableIfExists('{{%pragmaticanalytics_daily_stats}}');
        return true;
    }
}
