<?php


register_activation_hook(__FILE__, array('AutoLoginUrl', 'auto_login_url_activate'));

//停止時の設定
register_deactivation_hook(__FILE__, array('AutoLoginUrl', 'auto_login_url_unactivate'));

//アンインストール時の設定
register_uninstall_hook(__FILE__, array('AutoLoginUrl', 'auto_login_url_uninstall'));


class AutoLoginUrl
{
    function __construct()
    {
        //add_action('admin_menu', array($this, 'add_pages'));
        add_action("admin_menu",function(){
         add_options_page(
              'Auto_Login_Url', // page_title（オプションページのHTMLのタイトル>）
              'AutoLoginUrl', // menu_title（メニューで表示されるタイトル）
              'administrator', // capability
              'auto-login-url', // menu_slug（URLのスラッグこの例だとoptions-general.php?page=hello-world）
              array($this, 'auto_login_url_plugin')
         );
        });
        //if(is_admin()) {
            //管理画面（設定メニュー）
            //add_action('admin_init', array(&$this, 'auto_login_url_option_register'));

        //    add_action( 'admin_menu', array(&$this, 'add_plugin_admin_menu'));
        //}
    }

    function add_plugin_admin_menu() {
         add_options_page(
              'Auto_Login_Url', // page_title（オプションページのHTMLのタイトル）
              'AutoLoginUrl', // menu_title（メニューで表示されるタイトル）
              'administrator', // capability
              'auto-login-url', // menu_slug（URLのスラッグこの例だとoptions-general.php?page=hello-world）
              array(&$this,'add_pages') // function
         );
    }


    // https://celtislab.net/archives/20130515/wordpress%E3%83%97%E3%83%A9%E3%82%B0%E3%82%A4%E3%83%B3%E3%81%A7%E5%A4%96%E9%83%A8css%E3%83%95%E3%82%A1%E3%82%A4%E3%83%AB%E3%82%92%E4%BD%BF%E3%81%A3%E3%81%A6%E3%81%BF%E3%82%8B/
    public function auto_login_url_option_register()
    {
        register_setting('post6widget_optiongroup', 'post6widget_option');
        //管理画面の<head> 内でCSSファイルを読みこませる
        $urlpath = plugins_url('admin.css', __FILE__);
        wp_register_style('post6style', $urlpath);
        wp_enqueue_style('post6style');
        _log("myplgin_load");
    }

 
    function add_pages()
    {
        add_menu_page(
            'AutoLoginUrl Plugin Settings',
            'AutoLoginUrl',
            'manage_options',
            'AutoLoginUrlPluginMenu',
            array($this, 'auto_login_url_plugin')
        );
    } 

    /**
     * 管理画面のHTMLの生成と表示
     */
    function auto_login_url_plugin()
    {
        _log("auto_login_url_plugin");
        if (isset($_REQUEST["submit"]["detail"])) {
            //詳細
            self::detail();
        } else if (isset($_REQUEST["submit"]["edit"])) {
            //修正
            self::edit();
        } else if (isset($_REQUEST["submit"]["edit_check"])) {
            //修正確認
            self::edit_check();
        } else if (isset($_REQUEST["submit"]["edit_exec"])) {
            //修正実行
            self::edit_exec();
        } else if (isset($_REQUEST["submit"]["delete_check"])) {
            //削除確認
            self::delete_check();
        } else if (isset($_REQUEST["submit"]["delete_exec"])) {
            //削除実行
            self::delete_exec();
        } else if (isset($_REQUEST["submit"]["regist"])) {
            //新規登録
            self::regist();
        } else if (isset($_REQUEST["submit"]["regist_check"])) {
            //新規登録確認
            self::regist_check();
        } else if (isset($_REQUEST["submit"]["regist_exec"])) {
            //新規登録
            self::regist_exec();
        } else if (isset($_REQUEST["submit"]["regist_file"])) {
            //ファイルアップロード用画面
            self::regist_file();
        } else if (isset($_REQUEST["submit"]["regist_file_up"])) {
            //ファイルアップロード処理
            self::regist_file_up();
        } else {
            //初期表示
            self::disp();
        }
    }
 
    /**
     * 初期表示
     */
    function disp()
    {
        //データ一覧
        echo <<< EOL
    <form action="" method="post">
     
    <h2>データ一覧</h2>
    <input type='submit' name='submit[regist]' class='button-primary' value='新規登録' />
    <input type='submit' name='submit[regist_file]' class='button-primary' value='新規登録(ファイルアップロード)' />

     
    <div class="wrap">
     
    <table class="wp-list-table widefat striped posts">
        <tr>
            <th nowrap>ID</th>
            <th nowrap>名前</th>
            <th nowrap>登録日時</th>
            <th nowrap>詳細</th>
            <th nowrap>編集</th>
        </tr>
EOL;
     
        $pageid = filter_input(INPUT_GET, 'pageid');
        //1ページあたりの件数
        $limit = 10;

        global $wpdb;
     
        //全件数取得
        $tbl_name = $wpdb->prefix . 'sample_mst';
        $sql = "SELECT count(*) AS CNT FROM {$tbl_name}";
        $rows = $wpdb->get_results($sql);
        $recordcount = $rows[0]->CNT;
        _log("recordcount=".$recordcount);
        
        //offsetの値を決定
        $offset = $pageid * $limit;
        _log("offset=".$offset.",limit=".$limit);
        
        //offset と limitによる画面表示用のデータ取得
        $sql = "SELECT * FROM {$tbl_name} ORDER BY id limit {$offset}, {$limit}";
        //通常の取得方法(SQL実行結果を、オブジェクトとして取得)
        $rows = $wpdb->get_results($sql);

        foreach($rows as $row) {
            echo "<tr>";
            echo "<td>" . $row->id . "</td>";
            echo "<td>" . $row->sample_name . "</td>";
            echo "<td>" . $row->create_date . "</td>";
            echo "<td>";
            echo "<input type='submit' name='submit[detail][" . $row->id . "]'";
            echo " class='button-primary' value='詳細' />";
            echo "</td>";
            echo "<td>";
            echo "<input type='submit' name='submit[edit][" . $row->id . "]'";
            echo " class='button-primary' value='編集' />";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        echo "</form>";

         
        
        $args = array(
            'label' => __('Per Page'),
            'default' => 10,
            'option' => 'disp'
        );
        $page_html = self::pagination($recordcount);
        //ページ部分の表示
        echo "<div class='admin_pagination'>";
        echo "<ul>";
        foreach ($page_html as $key => $value) {
            echo "<li>" . $value . "</li>";
        }
        echo "</ul>";
    }

    function getCurrentPage(){
        $pageid = filter_input(INPUT_GET, 'pageid');
        if($pageid){
        return $pageid;
        }else{
            return 0;
        }
    }
 
    function pagination($recordcount)
    {
        $count = $recordcount;
        $limit = 10;
     
        //レコード総数がゼロのときは何も出力しない
        if (0 === $count) {
            return '';
        }
     
        //現在表示中のページ番号（ゼロスタート）
        $intCurrentPage = self::getCurrentPage();
     
        //ページの最大数
        $intMaxpage = ceil($count / $limit);
     
        //現在ページの前後３ページを出力
        $intStartpage = (2 < $intCurrentPage) ? $intCurrentPage - 3 : 0;
        $intEndpage = (($intStartpage + 7) < $intMaxpage) ? $intStartpage + 7 : $intMaxpage;
     
        //url組み立て
        $urlparams = filter_input_array(INPUT_GET);
     
        $items = [];
     
        //ページURLの生成
        //最初
        $urlparams['page'] = filter_input(INPUT_GET, 'page');
        $urlparams['pageid'] = 0;
        $items[] = sprintf('<span><a href="?%s">%s</a></span>'
            , http_build_query($urlparams)
            , '最初'
        );
     
        //表示中のページが先頭ではない時
        if (0 < $intCurrentPage) {
            $urlparams['pageid'] = $intCurrentPage - 1;
            $items[] = sprintf('<span><a href="?%s">%s</a></span>'
                , http_build_query($urlparams)
                , '前へ'
            );
        }
     
        for ($i = $intStartpage; $i < $intEndpage; $i++) {
            $urlparams['pageid'] = $i;
            $items[] = sprintf('<span%s><a href="?%s">%s</a></span>'
                , ($intCurrentPage == $i) ? ' class="current"' : ''
                , http_build_query($urlparams)
                , $i + 1
            );
        }
     
        //表示中のページが最後ではない時
        if ($intCurrentPage < $intMaxpage) {
            $urlparams['pageid'] = $intCurrentPage + 1;
            $items[] = sprintf('<span><a href="?%s">%s</a></span>'
                , http_build_query($urlparams)
                , '次へ'
            );
        }
     
        //最後
        $urlparams['pageid'] = $intMaxpage - 1;
        $items[] = sprintf('<span><a href="?%s">%s</a></span>'
            , http_build_query($urlparams)
            , '最後'
        );
     
        return $items;
    }

    /**
     * 詳細表示
     */
    function detail()
    {
        //押されたボタンのIDを取得する
        if (array_search("詳細", $_REQUEST["submit"]["detail"])) {
            $form_id = array_search("詳細", $_REQUEST["submit"]["detail"]);
        }
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ詳細</h2>
EOL;
 
        global $wpdb;
 
        $tbl_name = $wpdb->prefix . 'sample_mst';
        $sql = "SELECT * FROM {$tbl_name} WHERE id = %d;";
        $prepared = $wpdb->prepare($sql, $form_id);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
 
        echo <<<EOL
<table border="1">
    <tr>
        <td>ID</td>
        <td>{$rows[0]["id"]}</td>
    </tr>
    <tr>
        <td>NAME</td>
        <td>{$rows[0]["sample_name"]}</td>
    </tr>
    <tr>
        <td>登録日時</td>
        <td>{$rows[0]["create_date"]}</td>
    </tr>
</table>
<input type='submit' name='submit[]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }
 
    /**
     * 修正
     */
    function edit()
    {
        if (isset($_REQUEST["form_id"])) {
            $form_id = $_REQUEST["form_id"];
            $sample_name = $_REQUEST["sample_name"];
            $create_date = $_REQUEST["create_date"];
        } else {
            //押されたボタンのIDを取得する
            if (array_search("編集", $_REQUEST["submit"]["edit"])) {
                $form_id = array_search("編集", $_REQUEST["submit"]["edit"]);
            }
 
            global $wpdb;
 
            $tbl_name = $wpdb->prefix . 'sample_mst';
            $sql = "SELECT * FROM {$tbl_name} WHERE id = %d;";
            $prepared = $wpdb->prepare($sql, $form_id);
            $rows = $wpdb->get_results($prepared, ARRAY_A);
 
            $sample_name = $rows[0]["sample_name"];
            $create_date = $rows[0]["create_date"];
        }
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ編集</h2>
EOL;
 
 
        echo <<<EOL
<table border="1">
    <tr>
        <td>ID</td>
        <td>{$form_id}</td>
    </tr>
    <tr>
        <td>NAME</td>
        <td>
            <input type="text" name="sample_name" value="{$sample_name}">
        </td>
    </tr>
    <tr>
        <td>登録日時</td>
        <td>{$create_date}</td>
    </tr>
</table>
 
<input type="hidden" name="form_id" value="{$form_id}">
<input type="hidden" name="create_date" value="{$create_date}">
 
<input type='submit' name='submit[edit_check]' class='button-primary' value='編集内容を確認する' />
<input type='submit' name='submit[]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }
 
    /**
     * 編集確認
     */
    function edit_check()
    {
        $form_id = $_REQUEST["form_id"];
        $sample_name = $_REQUEST["sample_name"];
        $create_date = $_REQUEST["create_date"];
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ編集確認</h2>
EOL;
 
        echo <<<EOL
<table border="1">
    <tr>
        <td>ID</td>
        <td>{$form_id}</td>
    </tr>
    <tr>
        <td>NAME</td>
        <td>{$sample_name}</td>
    </tr>
</table>
 
<input type="hidden" name="form_id" value="{$form_id}">
<input type="hidden" name="sample_name" value="{$sample_name}">
<input type="hidden" name="create_date" value="{$create_date}">
 
<input type='submit' name='submit[edit_exec]' class='button-primary' value='編集する' />
<input type='submit' name='submit[edit]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }
 
    /**
     * 編集実行
     */
    function edit_exec()
    {
        global $wpdb;
 
        $form_id = $_REQUEST["form_id"];
        $sample_name = $_REQUEST["sample_name"];
        $update_date = date("Y-m-d H:i:s");
 
        //投稿を更新
        $tbl_name = $wpdb->prefix . 'sample_mst';
        $result = $wpdb->update(
            $tbl_name,
            array('sample_name' => $sample_name,),
            array('id' => $form_id,),
            array('%s'),
            array('%d')
        );
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ編集認</h2>
EOL;
 
        echo "<div class='updated fade'><p><strong>";
        echo _e('更新が完了しました');
        echo "</strong></p></div>";
 
        echo <<<EOL
<input type='submit' name='submit[]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }

    /*
     * 削除確認
     */
    function delete_check()
    {
        $form_id = $_REQUEST["form_id"];
 
        global $wpdb;
 
        $tbl_name = $wpdb->prefix . 'sample_mst';
        $sql = "SELECT * FROM {$tbl_name} WHERE id = %d;";
        $prepared = $wpdb->prepare($sql, $form_id);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ削除確認</h2>
    <table border="1">
        <tr>
            <td>ID</td>
            <td>{$form_id}</td>
        </tr>
        <tr>
            <td>NAME</td>
            <td>{$rows[0]["sample_name"]}</td>
        </tr>
        <tr>
            <td>登録日時</td>
            <td>{$rows[0]["create_date"]}</td>
        </tr>
    </table>
 
    <input type="hidden" name="form_id" value="{$form_id}">
 
    <input type='submit' name='submit[delete_exec]' class='button-primary' value='削除する' />
    <input type='submit' name='submit[edit]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }

    /**
     * 削除実行
     */
    function delete_exec()
    {
        $form_id = $_REQUEST["form_id"];
 
        //データを削除
        global $wpdb;
        $tbl_name = $wpdb->prefix . 'sample_mst';
        $sql = "DELETE FROM {$tbl_name} WHERE id = %s;";
        $dlt = $wpdb->query($wpdb->prepare($sql, $form_id));
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ編集認</h2>
EOL;
 
        echo "<div class='updated fade'><p><strong>";
        echo _e('削除が完了しました');
        echo "</strong></p></div>";
 
        echo <<<EOL
<input type='submit' name='submit[]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }


    /**
     * 登録
     */
    function regist()
    {
        if ($error_message_flg !== false) {
            $sample_name = esc_attr($_REQUEST["sample_name"]);
        }
 
        echo <<< EOL
<form action="" method="post">
    <h2>データ登録</h2>
EOL;
 
        //エラーメッセージのフラグがfalseの場合、メッセージを表示する
        if ($error_message_flg == false) {
            echo "<div class='updated fade'><p><strong>";
            echo _e('NAMEを入力してください');
            echo "</strong></p></div>";
        }
 
        echo <<< EOL
    <table border="1">
        <tr>
            <td>NAME</td>
            <td>
                <input type="text" name="sample_name" value="{$sample_name}">
            </td>
        </tr>
    </table>
 
    <input type='submit' name='submit[regist_check]' class='button-primary' value='登録内容を確認する' />
    <input type='submit' name='submit[]' class='button-primary' value='戻る' />
</form>
EOL;
    }
 
    /**
     * 登録確認
     */
    function regist_check()
    {
        //入力値が空白の場合の処理(ここでは単純に0バイトだったら)
        if (!strlen($_REQUEST["sample_name"])) {
            self::regist(false);
            return;
        }
         
        $sample_name = esc_attr($_REQUEST["sample_name"]);
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ登録確認</h2>
<table border="1">
    <tr>
        <td>NAME</td>
        <td>{$sample_name}</td>
    </tr>
</table>
 
<input type="hidden" name="sample_name" value="{$sample_name}">
 
<input type='submit' name='submit[regist_exec]' class='button-primary' value='登録する' />
<input type='submit' name='submit[regist]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }
 
 
    /**
     * 登録実行
     */
    function regist_exec()
    {
        global $wpdb;
 
        $sample_name = $_REQUEST["sample_name"];
 
        //投稿を登録
        $table_name = $wpdb->prefix . 'sample_mst';
        $result = $wpdb->insert(
            $table_name,
            array(
                'sample_name' => $sample_name,
                'create_date' => current_time('mysql')
            )
        );
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ登録</h2>
    <div class='updated fade'><p><strong>
EOL;
        echo _e('登録が完了しました');
        echo <<<EOL
</strong></p></div>
<input type='submit' name='submit[]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }


    function regist_file($error_message_flg = null)
    {
        echo <<< EOL
    <h2>ファイルアップロード</h2>
    <div class="wrap">
    <div class="wrap">
    <table class="wp-list-table widefat striped posts">
        <tr>
            <td>ファイルアップロード</td>
            <td>
                <form method="post" action="" enctype="multipart/form-data">
                    CSVを選択してアップロードボタンを押してください<br />
                    <input type="file" name="upfilename" />
                    <input type="submit" value="アップロード">
                    <input type="hidden" name="submit[regist_file_up]" value="on">
                </form>
     
            </td>
        </tr>
    </table>
    </div>
    <form action="" method="post">
        <input type='submit' name='submit[file_upload]' class='button-primary' value='戻る' />
        <input type="hidden" name="form_id" value="{$form_id}">
        <input type="hidden" name="create_date" value="{$create_date}">
    </form>
    </div>
EOL;
     
    }
    
    function regist_file_up()
    {
        //CSVファイルがアップロードされた場合
        if (is_uploaded_file($_FILES["upfilename"]["tmp_name"])) {
            $upload_dir = wp_upload_dir();
            $upload_file_name = $upload_dir['basedir'] . "/" . $_FILES["upfilename"]["name"];
            if (move_uploaded_file($_FILES["upfilename"]["tmp_name"], $upload_file_name)) {
                chmod($upload_file_name, 0777);
            }
            $message = "ファイルをアップロードいたしました";
        } else {
            $message = "ファイルのアップロードが失敗しました";
        }
     
        //完了メッセージの出力
        echo <<< EOL
    <h2>ファイルアップロード完了</h2>
    <form action="" method="post">
    <div class="wrap">
        {$message}
    </div>
    <input type='submit' name='submit[]' class='button-primary' value='戻る' />
    </form>
EOL;
     
    }



    function auto_login_url_activate()
    {
        //テーブル作成などなど
        self::create_tables_sample_mst();     
          
    }
 
    function create_tables_sample_mst()
    {
        global $wpdb;
 
        $charset_collate = "";
 
        //接頭辞の追加(socal_count_cache)
        $table_name = $wpdb->prefix . 'sample_mst';
 
        //charsetを指定する
        if (!empty($wpdb->charset)) {
            $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} ";
        }
 
        //照合順序を指定する(ある場合、通常デフォルトのutf8_general_ci)
        if (!empty($wpdb->collate)) {
            $charset_collate .= "COLLATE {$wpdb->collate}";
        }
 
        $sql = <<< EOL
CREATE TABLE {$table_name} (
id              INT NOT NULL AUTO_INCREMENT,
sample_name     VARCHAR(128),
create_date     DATETIME,
PRIMARY KEY(id)
) {$charset_collate};
EOL;
 
        //dbDeltaを実行する為に必要
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
    * 停止時の実行
    */
    function auto_login_url_unactivate()
    {
        //テーブルデータ消去などなど
        global $wpdb;
     
        //削除するテーブルの決定
        $table_name = $wpdb->prefix . 'sample_mst';
     
        //テーブル削除
        $sql_delete = "DELETE FROM " . $table_name . ";";
        $wpdb->query($sql_delete);
    }

    /**
     * 停止時の実行
     */
    function auto_login_url_uninstall()
    {
        global $wpdb;
     
        //削除するテーブル名の決定
        $table_name = $wpdb->prefix . 'sample_mst';
     
        //テーブル削除
        $sql_drop = "DROP TABLE " . $table_name . ";";
        $wpdb->query($sql_drop);
    }
}
 
new AutoLoginUrl; 