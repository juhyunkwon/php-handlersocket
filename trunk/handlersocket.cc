
#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

extern "C" {
#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_handlersocket.h"
}

#include <sstream>
#include "handlersocket/hstcpcli.hpp"

/* HandlerSocket */
typedef struct
{
    zend_object object;
    dena::hstcpcli_i *cli;
    long error_no;
    zval *error_str;
} php_handlersocket_t;

#define HANDLERSOCKET_OBJECT \
    php_handlersocket_t *hs; \
    hs = (php_handlersocket_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

#define HANDLERSOCKET_EXECUTE_INSERT "+"
#define HANDLERSOCKET_EXECUTE_UPDATE "U"
#define HANDLERSOCKET_EXECUTE_DELETE "D"

#if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 3)
#   define array_init_size(arg, size) array_init(arg)
#endif

static zend_class_entry *handlersocket_ce = NULL;

static ZEND_METHOD(handlersocket, __construct);
static ZEND_METHOD(handlersocket, __destruct);
static ZEND_METHOD(handlersocket, openIndex);
static ZEND_METHOD(handlersocket, executeSingle);
static ZEND_METHOD(handlersocket, executeMulti);
static ZEND_METHOD(handlersocket, executeUpdate);
static ZEND_METHOD(handlersocket, executeDelete);
static ZEND_METHOD(handlersocket, executeInsert);
static ZEND_METHOD(handlersocket, getError);

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs___construct, 0, 0, 2)
    ZEND_ARG_INFO(0, host)
    ZEND_ARG_INFO(0, port)
    ZEND_ARG_INFO(0, options)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs___destruct, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_openIndex, 0, 0, 5)
    ZEND_ARG_INFO(0, id)
    ZEND_ARG_INFO(0, db)
    ZEND_ARG_INFO(0, table)
    ZEND_ARG_INFO(0, index)
    ZEND_ARG_INFO(0, fields)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_executeSingle, 0, 0, 3)
    ZEND_ARG_INFO(0, id)
    ZEND_ARG_INFO(0, op)
    ZEND_ARG_INFO(0, fields)
    ZEND_ARG_INFO(0, limit)
    ZEND_ARG_INFO(0, skip)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_executeMulti, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_executeUpdate, 0, 0, 4)
    ZEND_ARG_INFO(0, id)
    ZEND_ARG_INFO(0, op)
    ZEND_ARG_INFO(0, fields)
    ZEND_ARG_INFO(0, values)
    ZEND_ARG_INFO(0, limit)
    ZEND_ARG_INFO(0, skip)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_executeDelete, 0, 0, 3)
    ZEND_ARG_INFO(0, id)
    ZEND_ARG_INFO(0, op)
    ZEND_ARG_INFO(0, fields)
    ZEND_ARG_INFO(0, limit)
    ZEND_ARG_INFO(0, skip)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_executeInsert, 0, 0, 2)
    ZEND_ARG_INFO(0, id)
    ZEND_ARG_INFO(0, fields)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_getError, 0, 0, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry handlersocket_methods[] = {
    ZEND_ME(handlersocket, __construct,
            arginfo_hs___construct, ZEND_ACC_PUBLIC)
    ZEND_ME(handlersocket, __destruct,
            arginfo_hs___destruct, ZEND_ACC_PUBLIC)
    ZEND_ME(handlersocket, openIndex,
            arginfo_hs_openIndex, ZEND_ACC_PUBLIC)
    ZEND_ME(handlersocket, executeSingle,
            arginfo_hs_executeSingle, ZEND_ACC_PUBLIC)
    ZEND_ME(handlersocket, executeMulti,
            arginfo_hs_executeMulti, ZEND_ACC_PUBLIC)
    ZEND_ME(handlersocket, executeUpdate,
            arginfo_hs_executeUpdate, ZEND_ACC_PUBLIC)
    ZEND_ME(handlersocket, executeDelete,
            arginfo_hs_executeDelete, ZEND_ACC_PUBLIC)
    ZEND_ME(handlersocket, executeInsert,
            arginfo_hs_executeInsert, ZEND_ACC_PUBLIC)
    ZEND_ME(handlersocket, getError,
            arginfo_hs_getError, ZEND_ACC_PUBLIC)
    ZEND_MALIAS(handlersocket, executeFind, executeSingle,
                arginfo_hs_executeSingle, ZEND_ACC_PUBLIC)
    {NULL, NULL, NULL}
};

static void php_handlersocket_free(php_handlersocket_t *hs TSRMLS_DC)
{
#if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION > 0)
    zend_object_std_dtor(&hs->object TSRMLS_CC);
#else
    if (hs->object.properties)
    {
        zend_hash_destroy(hs->object.properties);
        FREE_HASHTABLE(hs->object.properties);
    }
#endif
    efree(hs);
}

static zend_object_value php_handlersocket_new(zend_class_entry *ce TSRMLS_DC)
{
    zend_object_value retval;
    zval *tmp;
    php_handlersocket_t *hs;

    hs = (php_handlersocket_t *)emalloc(sizeof(php_handlersocket_t));

#if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION > 0)
    zend_object_std_init(&hs->object, ce TSRMLS_CC);
#else
    ALLOC_HASHTABLE(hs->object.properties);
    zend_hash_init(hs->object.properties, 0, NULL, ZVAL_PTR_DTOR, 0);
    hs->object.ce = ce;
#endif

    zend_hash_copy(
        hs->object.properties, &ce->default_properties,
        (copy_ctor_func_t)zval_add_ref, (void *)&tmp, sizeof(zval *));

    retval.handle = zend_objects_store_put(
        hs, (zend_objects_store_dtor_t)zend_objects_destroy_object,
        (zend_objects_free_object_storage_t)php_handlersocket_free,
        NULL TSRMLS_CC);
    retval.handlers = zend_get_std_object_handlers();

    return retval;
}

PHP_MINIT_FUNCTION(handlersocket)
{
    /* class */
    zend_class_entry ce;

    INIT_CLASS_ENTRY(
        ce, "HandlerSocket", (zend_function_entry *)handlersocket_methods);
    handlersocket_ce = zend_register_internal_class(&ce TSRMLS_CC);
    handlersocket_ce->create_object = php_handlersocket_new;

    /* constant */
#if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION > 0)
    zend_declare_class_constant_string(
        handlersocket_ce, "PRIMARY", strlen("PRIMARY"), "PRIMARY" TSRMLS_CC);
#else
    REGISTER_STRING_CONSTANT(
    "HANDLERSOCKET_PRIMARY", "PRIMARY", CONST_CS | CONST_PERSISTENT);
#endif

    return SUCCESS;
}

/*
PHP_MSHUTDOWN_FUNCTION(handlersocket)
{
    return SUCCESS;
}

PHP_RINIT_FUNCTION(handlersocket)
{
    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(handlersocket)
{
    return SUCCESS;
}
*/

PHP_MINFO_FUNCTION(handlersocket)
{
    php_info_print_table_start();
    php_info_print_table_row(2, "Handler Socket support", "enabled");
    php_info_print_table_row(
        2, "extension Version", HANDLERSOCKET_EXTENSION_VERSION);
    php_info_print_table_end();

}

zend_module_entry handlersocket_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
    STANDARD_MODULE_HEADER,
#endif
    "handlersocket",
    NULL,
    PHP_MINIT(handlersocket),
    NULL,
    NULL,
    NULL,
    PHP_MINFO(handlersocket),
#if ZEND_MODULE_API_NO >= 20010901
    HANDLERSOCKET_EXTENSION_VERSION,
#endif
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_HANDLERSOCKET
ZEND_GET_MODULE(handlersocket)
#endif


inline static std::string long_to_string(long n)
{
    std::ostringstream s;
    s << std::dec << n;
    return s.str();
}

inline static void array_to_vector(zval *ary, std::vector<dena::string_ref>& vec)
{
    HashTable *ht;
    size_t i, num;
    char *key;
    uint key_len;
    int key_type;
    ulong key_index;
    zval **data;
    HashPosition pos;
    TSRMLS_FETCH();

    if (ary == NULL)
    {
        vec.push_back(dena::string_ref());
        return;
    }

    ht = HASH_OF(ary);
    num = zend_hash_num_elements(ht);

    if (num == 0)
    {
        vec.push_back(dena::string_ref());
        return;
    }

    vec.reserve(num);

    zend_hash_internal_pointer_reset_ex(ht, &pos);
    for (;; zend_hash_move_forward_ex(ht, &pos))
    {
        key_type = zend_hash_get_current_key_ex(
            ht, &key, &key_len, &key_index, 0, &pos);

        if (key_type == HASH_KEY_NON_EXISTANT)
        {
            break;
        }

        if (zend_hash_get_current_data_ex(ht, (void **)&data, &pos) != SUCCESS)
        {
            continue;
        }

        if (Z_TYPE_PP(data) == IS_STRING)
        {
            vec.push_back(dena::string_ref(Z_STRVAL_PP(data), Z_STRLEN_PP(data)));
        }
        else if (Z_TYPE_PP(data) == IS_LONG ||
                 Z_TYPE_PP(data) == IS_DOUBLE ||
                 Z_TYPE_PP(data) == IS_BOOL)
        {
            convert_to_string(*data);
            vec.push_back(dena::string_ref(Z_STRVAL_PP(data), Z_STRLEN_PP(data)));
        }
        else
        {
            vec.push_back(dena::string_ref());
        }
    }
}

inline static void array_to_conf(zval *opts, dena::config& conf)
{
    char *key;
    uint key_len;
    int key_type;
    ulong key_index;
    zval **data;
    HashPosition pos;
    HashTable *ht;
    TSRMLS_FETCH();

    if (opts == NULL)
    {
        return;
    }

    ht = HASH_OF(opts);

    zend_hash_internal_pointer_reset_ex(ht, &pos);
    for (;; zend_hash_move_forward_ex(ht, &pos))
    {
        key_type = zend_hash_get_current_key_ex(
            ht, &key, &key_len, &key_index, 0, &pos);

        if (key_type == HASH_KEY_NON_EXISTANT)
        {
            break;
        }

        if (zend_hash_get_current_data_ex(ht, (void **)&data, &pos) != SUCCESS)
        {
            continue;
        }

        if (strcmp(key, "host") == 0)
        {
            conf["host"] = std::string(Z_STRVAL_PP(data));
        }
        else if (strcmp(key, "port") == 0)
        {
            conf["port"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "timeout") == 0)
        {
            conf["timeout"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "listen_backlog") == 0)
        {
            conf["listen_backlog"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "sndbuf") == 0)
        {
            conf["sndbuf"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "rcvbuf") == 0)
        {
            conf["rcvbuf"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "use_epoll") == 0)
        {
            conf["use_epoll"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "num_threads") == 0)
        {
            conf["num_threads"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "readsize") == 0)
        {
            conf["readsize"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "accept_balance") == 0)
        {
            conf["accept_balance"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "wrlock_timeout") == 0)
        {
            conf["wrlock_timeout"] = long_to_string(Z_LVAL_PP(data));
        }
        else if (strcmp(key, "for_write") == 0)
        {
            conf["for_write"] = long_to_string(Z_LVAL_PP(data));
        }
    }
}

static inline void handlersocket_prepare(
    dena::hstcpcli_i *const cli, size_t id, char *op, zval *fields,
    long limit, long skip, char *modop, zval *values)
{
    dena::string_ref op_ref, modop_ref;
    std::vector<dena::string_ref> fldary, valary;

    op_ref = dena::string_ref(op, strlen(op));
    array_to_vector(fields, fldary);

    if (modop != NULL)
    {
        modop_ref = dena::string_ref(modop, strlen(modop));
        if (values != NULL)
        {
            array_to_vector(values, valary);
        }
    }

    cli->request_buf_exec_generic(
        id, op_ref, &fldary[0], fldary.size(), limit, skip,
        modop_ref, &valary[0], valary.size());
}

static inline void handlersocket_set_error(zval *error, const char *str)
{
    if (error)
    {
        zval_ptr_dtor(&error);
        ALLOC_INIT_ZVAL(error);
    }
    ZVAL_STRING(error, (char *)str, 1);
}

static inline void handlersocket_get_results(
    dena::hstcpcli_i *const cli, zval *return_value, size_t flds)
{
    size_t i;

    array_init(return_value);

    const dena::string_ref *row = 0;
    while ((row = cli->get_next_row()) != 0)
    {
        zval *value;

        ALLOC_INIT_ZVAL(value);
        array_init_size(value, flds);

        for (i = 0; i < flds; ++i)
        {
            const dena::string_ref& v = row[i];
            if (v.begin() != 0)
            {
                add_next_index_stringl(value, (char *)v.begin(), v.size(), 1);
            }
            else
            {
                add_next_index_null(value);
            }
        }

        add_next_index_zval(return_value, value);
    }
}

static inline int handlersocket_execute(
    dena::hstcpcli_i *const cli, zval *return_value,
    long id, char *op, zval *fields, long limit, long skip,
    char *modop, zval *values)
{
    size_t num_flds = 0;
    int result;

    if (strlen(op) == 0)
    {
        ZVAL_BOOL(return_value, 0);
        return -1;
    }

    handlersocket_prepare(cli, id, op, fields, limit, skip, modop, values);

    if (cli->request_send() != 0)
    {
        ZVAL_BOOL(return_value, 0);
        return cli->get_error_code();
    }

    result = cli->response_recv(num_flds);

    if (result != 0)
    {
        ZVAL_BOOL(return_value, 0);
    }
    else if (strcmp(op, HANDLERSOCKET_EXECUTE_INSERT) == 0 ||
             (modop != NULL &&
              (strcmp(modop, HANDLERSOCKET_EXECUTE_UPDATE) == 0 ||
               strcmp(modop, HANDLERSOCKET_EXECUTE_DELETE) == 0)))
    {
        ZVAL_BOOL(return_value, 1);
    }
    else
    {
        handlersocket_get_results(cli, return_value, num_flds);
    }

    if (result >= 0)
    {
        cli->response_buf_remove();
    }

    return cli->get_error_code();
}


static ZEND_METHOD(handlersocket, __construct)
{
    HANDLERSOCKET_OBJECT;
    char *host;
    int host_len;
    int port;
    zval *opts = NULL;

    dena::config conf;
    dena::socket_args args;
    dena::hstcpcli_ptr cli;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "sl|a",
            &host, &host_len, &port, &opts) == FAILURE)
    {
        return;
    }

    if (strlen(host) == 0)
    {
        RETURN_NULL();
    }

    conf["host"] = std::string(host);
    conf["port"] = long_to_string(port);

    array_to_conf(opts, conf);

    args.set(conf);

    cli = dena::hstcpcli_i::create(args);
    hs->cli = cli.get();
    cli.release();

    hs->error_no = 0;
    ALLOC_INIT_ZVAL(hs->error_str);
}

static ZEND_METHOD(handlersocket, __destruct)
{
    HANDLERSOCKET_OBJECT;

    if (hs->cli)
    {
        hs->cli->close();
        delete hs->cli;
    }

    if (hs->error_str)
    {
        zval_ptr_dtor(&hs->error_str);
    }
}

static ZEND_METHOD(handlersocket, openIndex)
{
    HANDLERSOCKET_OBJECT;
    long id;
    char *db, *table, *index, *fields;
    int db_len, table_len, index_len, fields_len, result;
    size_t num_flds;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "lssss",
            &id, &db, &db_len, &table, &table_len,
            &index, &index_len, &fields, &fields_len) == FAILURE)
    {
        return;
    }

    hs->cli->request_buf_open_index(id, db, table, index, fields);

    if (hs->cli->get_error_code() < 0)
    {
        hs->error_no = hs->cli->get_error_code();
        handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
        RETURN_FALSE;
    }

    if (hs->cli->request_send() != 0)
    {
        hs->error_no = hs->cli->get_error_code();
        handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
        RETURN_FALSE;
    }

    result = hs->cli->response_recv(num_flds);
    if (result >= 0)
    {
        hs->cli->response_buf_remove();
    }
    else
    {
        hs->error_no = hs->cli->get_error_code();
        handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
        RETURN_FALSE;
    }

    RETURN_TRUE;
}

static ZEND_METHOD(handlersocket, executeSingle)
{
    HANDLERSOCKET_OBJECT;
    long id;
    char *op;
    int op_len, modop_len;
    zval *fields;
    long limit = 1, skip = 0;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "lsa|ll",
            &id, &op, &op_len, &fields, &limit, &skip) == FAILURE)
    {
        return;
    }

    hs->error_no = handlersocket_execute(
        hs->cli, return_value, id, op, fields, limit, skip, NULL, NULL);

    if (hs->error_no != 0)
    {
        handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
    }
}

static ZEND_METHOD(handlersocket, executeMulti)
{
    HANDLERSOCKET_OBJECT;
    zval *args;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "a", &args) == FAILURE)
    {
        return;
    }

    HashTable *ht1 = HASH_OF(args);
    size_t num = zend_hash_num_elements(ht1);

    if (num == 0)
    {
        RETURN_FALSE;
    }

    char *key;
    uint key_len;
    int key_type;
    ulong key_index;
    zval **data;
    HashPosition pos;

    zend_hash_internal_pointer_reset_ex(ht1, &pos);
    for (;; zend_hash_move_forward_ex(ht1, &pos))
    {
        key_type = zend_hash_get_current_key_ex(
            ht1, &key, &key_len, &key_index, 0, &pos);

        if (key_type == HASH_KEY_NON_EXISTANT)
        {
            break;
        }

        if (zend_hash_get_current_data_ex(ht1, (void **)&data, &pos) != SUCCESS)
        {
            continue;
        }

        if (Z_TYPE_PP(data) != IS_ARRAY)
        {
            zval_ptr_dtor(data);
            continue;
        }

        HashTable *ht2 = HASH_OF(*data);
        size_t num2 = zend_hash_num_elements(ht2);

        if (num2 < 3)
        {
            RETURN_FALSE;
        }

        long id = 0;
        zval *values = NULL;
        long limit = 1, skip = 0;
        char *modop = NULL;
        zval *modvals = NULL;

        zval *op = NULL;
        zval *modop_data = NULL;

        char *key2;
        uint key_len2;
        int key_type2;
        ulong key_index2;
        zval **data2;
        HashPosition pos2;

        zend_hash_internal_pointer_reset_ex(ht2, &pos2);
        for (;; zend_hash_move_forward_ex(ht2, &pos2))
        {
            key_type2 = zend_hash_get_current_key_ex(
                ht2, &key2, &key_len2, &key_index2, 0, &pos2);

            if (key_type2 == HASH_KEY_NON_EXISTANT)
            {
                break;
            }

            if (zend_hash_get_current_data_ex(
                    ht2, (void **)&data2, &pos2) != SUCCESS)
            {
                continue;
            }

            switch (key_index2)
            {
                case 0:
                    id = Z_LVAL_PP(data2);
                    break;
                case 1:
                    ALLOC_INIT_ZVAL(op);
                    *op = **data2;
                    zval_copy_ctor(op);
                    break;
                case 2:
                    ALLOC_INIT_ZVAL(values);
                    *values = **data2;
                    zval_copy_ctor(values);
                    break;
                case 3:
                    limit = Z_LVAL_PP(data2);
                    break;
                case 4:
                    skip = Z_LVAL_PP(data2);
                    break;
                case 5:
                    ALLOC_INIT_ZVAL(modop_data);
                    *modop_data = **data2;
                    zval_copy_ctor(modop_data);
                    modop = Z_STRVAL_P(modop_data);
                    break;
                case 6:
                    ALLOC_INIT_ZVAL(modvals);
                    *modvals = **data2;
                    zval_copy_ctor(modvals);
                    break;
                default:
                    break;
            }
        }

        handlersocket_prepare(
            hs->cli, id, Z_STRVAL_P(op), values, limit, skip,
            modop, modvals);

        if (op != NULL)
        {
            zval_ptr_dtor(&op);
        }
        if (values != NULL)
        {
            zval_ptr_dtor(&values);
        }
        if (modop_data != NULL)
        {
            zval_ptr_dtor(&modop_data);
        }
        if (modvals != NULL)
        {
            zval_ptr_dtor(&modvals);
        }
    }

    if (hs->cli->request_send() != 0)
    {
        hs->error_no = hs->cli->get_error_code();
        handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
        RETURN_FALSE;
    }

    array_init(return_value);

    for (size_t i = 0; i < num; i++)
    {
        size_t num_flds = 0;
        int result = hs->cli->response_recv(num_flds);

        if (result != 0)
        {
            hs->error_no = hs->cli->get_error_code();
            handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
            add_next_index_bool(return_value, 0);
        }
        /*
        else if (strcmp(op, HANDLERSOCKET_EXECUTE_INSERT) == 0 ||
                 (modop != NULL &&
                  (strcmp(modop, HANDLERSOCKET_EXECUTE_UPDATE) == 0 ||
                   strcmp(modop, HANDLERSOCKET_EXECUTE_DELETE) == 0)))
        {
            ZVAL_BOOL(return_value, 1);
        }
        */
        else
        {
            zval *value;
            ALLOC_INIT_ZVAL(value);

            handlersocket_get_results(hs->cli, value, num_flds);

            add_next_index_zval(return_value, value);
        }

        if (result >= 0)
        {
            hs->cli->response_buf_remove();
        }
    }
}

static ZEND_METHOD(handlersocket, executeUpdate)
{
    HANDLERSOCKET_OBJECT;
    long id;
    char *op;
    long op_len;
    zval *fields, *values;
    long limit = 1, skip = 0;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "lsaa|ll",
            &id, &op, &op_len, &fields, &values,
            &limit, &skip) == FAILURE)
    {
        return;
    }

    hs->error_no = handlersocket_execute(
        hs->cli, return_value, id, op, fields, limit, skip,
        HANDLERSOCKET_EXECUTE_UPDATE, values);

    if (hs->error_no != 0)
    {
        handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
    }
}

static ZEND_METHOD(handlersocket, executeDelete)
{
    HANDLERSOCKET_OBJECT;
    long id;
    char *op;
    long op_len;
    zval *fields, *tmp;
    long limit = 1, skip = 0;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "lsa|ll",
            &id, &op, &op_len, &fields, &limit, &skip) == FAILURE)
    {
        return;
    }

    ALLOC_INIT_ZVAL(tmp);
    array_init(tmp);

    hs->error_no = handlersocket_execute(
        hs->cli, return_value, id, op, fields, limit, skip,
        HANDLERSOCKET_EXECUTE_DELETE, tmp);

    if (hs->error_no != 0)
    {
        handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
    }

    zval_ptr_dtor(&tmp);
}

static ZEND_METHOD(handlersocket, executeInsert)
{
    HANDLERSOCKET_OBJECT;
    long id;
    zval *values;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "la",
            &id, &values) == FAILURE)
    {
        return;
    }

    hs->error_no = handlersocket_execute(
        hs->cli, return_value,
        id, HANDLERSOCKET_EXECUTE_INSERT, values, 0, 0, NULL, NULL);

    if (hs->error_no != 0)
    {
        handlersocket_set_error(hs->error_str, hs->cli->get_error().c_str());
    }
}

static ZEND_METHOD(handlersocket, getError)
{
    HANDLERSOCKET_OBJECT;

    if (hs->error_no == 0)
    {
        RETURN_FALSE;
    }

    char* message = (char *)emalloc(Z_STRLEN_P(hs->error_str) + 32);

    sprintf(message, "%d:%s", hs->error_no, Z_STRVAL_P(hs->error_str));

    RETVAL_STRING(message, 1);

    efree(message);
}
