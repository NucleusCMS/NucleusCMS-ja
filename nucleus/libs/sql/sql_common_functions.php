<?php

function sql_get_server_version($conn_or_dbh = null)
{
    $dbh = ( ! empty($conn_or_dbh) ? $conn_or_dbh : sql_get_db());

    return implode(
        '.',
        array_map('intval', explode('.', sql_get_server_info($dbh)))
    );
}

function fix_mysql_sqlmode($conn_or_dbh = null)
{
    $dbh = ( ! empty($conn_or_dbh) ? $conn_or_dbh : sql_get_db());
    if (version_compare(sql_get_server_version($dbh), '5.6.0', '<')) {
        return;
    }
    // MySQL 8.0 : ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION
    // Error reporting on forums : ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE
    $options = array(
        'PIPES_AS_CONCAT', //  || is string concatenation in standard SQL
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ENGINE_SUBSTITUTION'
    );
    $new_sqlmode = implode(',', $options);
    sql_query(sprintf("SET SESSION sql_mode = '%s';", $new_sqlmode), $dbh);
}
