
#define HS_DEBUG 1
/*
  zval *z;
  MAKE_STD_ZVAL(z);
  ZVAL_STRINGL(z, request.c, request.len, 1);
  php_var_dump(&z, 1 TSRMLS_CC);
  zval_ptr_dtor(&z);
*/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "php_network.h"
#include "php_streams.h"
#include "zend_exceptions.h"
#include "ext/standard/file.h"
#include "ext/standard/info.h"
#include "ext/standard/php_smart_str.h"

#ifdef HS_DEBUG
#include "ext/standard/php_var.h" //DEBUG
#endif

#include "php_handlersocket.h"

#define HS_PRIMARY "PRIMARY"

#define HS_PROTOCOL_INSERT  "+"
#define HS_PROTOCOL_FILTER  "F"
#define HS_PROTOCOL_WHILE   "W"
#define HS_PROTOCOL_IN      "@"

#define HS_FIND_EQUAL         "="
#define HS_FIND_LESS          "<"
#define HS_FIND_LESS_EQUAL    "<="
#define HS_FIND_GREATER       ">"
#define HS_FIND_GREATER_EQUAL ">="

#define HS_MODIFY_UPDATE         "U"
#define HS_MODIFY_INCREMENT      "+"
#define HS_MODIFY_DECREMENT      "-"
#define HS_MODIFY_REMOVE         "D"
#define HS_MODIFY_UPDATE_PREV    "U?"
#define HS_MODIFY_INCREMENT_PREV "+?"
#define HS_MODIFY_DECREMENT_PREV "-?"
#define HS_MODIFY_REMOVE_PREV    "D?"

static zend_class_entry *hs_ce = NULL;
static zend_class_entry *hs_index_ce = NULL;
static zend_class_entry *hs_exception_ce = NULL;

typedef struct
{
    zend_object object;
    double timeout;
    zval *server;
    php_stream *stream;
} php_hs_t;

typedef struct
{
    zend_object object;
    zval *link;
    zval *filter;
} php_hs_index_t;

# define PUSH_PARAM(arg) zend_vm_stack_push(arg TSRMLS_CC)
# define POP_PARAM() (void)zend_vm_stack_pop(TSRMLS_C)

#define HS_METHOD_BASE(classname, name) zim_##classname##_##name

#define HS_METHOD_HELPER(classname, name, retval, thisptr, num, param) \
  PUSH_PARAM(param); PUSH_PARAM((void*)num); \
  HS_METHOD_BASE(classname, name)(num, retval, NULL, thisptr, 0 TSRMLS_CC); \
  POP_PARAM(); POP_PARAM();

#define HS_METHOD6(classname, name, retval, thisptr, param1, param2, param3, param4, param5, param6) \
  PUSH_PARAM(param1); PUSH_PARAM(param2); PUSH_PARAM(param3); PUSH_PARAM(param4);; PUSH_PARAM(param5); \
  HS_METHOD_HELPER(classname, name, retval, thisptr, 6, param6); \
  POP_PARAM(); POP_PARAM(); POP_PARAM(); POP_PARAM(); POP_PARAM();

#define HS_METHOD7(classname, name, retval, thisptr, param1, param2, param3, param4, param5, param6, param7) \
  PUSH_PARAM(param1); PUSH_PARAM(param2); PUSH_PARAM(param3); PUSH_PARAM(param4); PUSH_PARAM(param5); PUSH_PARAM(param6); \
  HS_METHOD_HELPER(classname, name, retval, thisptr, 7, param7); \
  POP_PARAM(); POP_PARAM(); POP_PARAM(); POP_PARAM(); POP_PARAM(); POP_PARAM();

/*
#define HS_GET_OBJ(obj)                                         \
  hs = (php_hs_t*)zend_object_store_get_object((obj) TSRMLS_CC);    \
*/

static ZEND_METHOD(HandlerSocket, __construct);
static ZEND_METHOD(HandlerSocket, __destruct);
static ZEND_METHOD(HandlerSocket, openIndex);

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
    ZEND_ARG_INFO(0, column)
    ZEND_ARG_INFO(0, options)
ZEND_END_ARG_INFO()

static const zend_function_entry hs_methods[] = {
    ZEND_ME(HandlerSocket, __construct, arginfo_hs___construct, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocket, __destruct, arginfo_hs___destruct, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocket, openIndex, arginfo_hs_openIndex, ZEND_ACC_PUBLIC)
    {NULL, NULL, NULL}
};

static ZEND_METHOD(HandlerSocketIndex, __construct);
static ZEND_METHOD(HandlerSocketIndex, __destruct);
static ZEND_METHOD(HandlerSocketIndex, getId);
static ZEND_METHOD(HandlerSocketIndex, getDatabase);
static ZEND_METHOD(HandlerSocketIndex, getTable);
static ZEND_METHOD(HandlerSocketIndex, getName);
static ZEND_METHOD(HandlerSocketIndex, getColumn);
static ZEND_METHOD(HandlerSocketIndex, getFilter);
static ZEND_METHOD(HandlerSocketIndex, find);
static ZEND_METHOD(HandlerSocketIndex, insert);
static ZEND_METHOD(HandlerSocketIndex, update);
static ZEND_METHOD(HandlerSocketIndex, remove);

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index___construct, 0, 0, 6)
    ZEND_ARG_INFO(0, hs)
    ZEND_ARG_INFO(0, id)
    ZEND_ARG_INFO(0, db)
    ZEND_ARG_INFO(0, table)
    ZEND_ARG_INFO(0, index)
    ZEND_ARG_INFO(0, column)
    ZEND_ARG_INFO(0, options)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index___destruct, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_getId, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_getDatabase, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_getTable, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_getName, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_getColumn, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_getFilter, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_find, 0, 0, 1)
    ZEND_ARG_INFO(0, query)
    ZEND_ARG_INFO(0, limit)
    ZEND_ARG_INFO(0, offset)
    ZEND_ARG_INFO(0, options)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_insert, 0, 0, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_update, 0, 0, 2)
    ZEND_ARG_INFO(0, values)
    ZEND_ARG_INFO(0, query)
    ZEND_ARG_INFO(0, limit)
    ZEND_ARG_INFO(0, offset)
    ZEND_ARG_INFO(0, options)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_hs_index_remove, 0, 0, 1)
    ZEND_ARG_INFO(0, query)
    ZEND_ARG_INFO(0, limit)
    ZEND_ARG_INFO(0, offset)
    ZEND_ARG_INFO(0, options)
ZEND_END_ARG_INFO()

static const zend_function_entry hs_index_methods[] = {
    ZEND_ME(HandlerSocketIndex, __construct, arginfo_hs_index___construct, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, __destruct, arginfo_hs_index___destruct, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, getId, arginfo_hs_index_getId, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, getDatabase, arginfo_hs_index_getDatabase, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, getTable, arginfo_hs_index_getTable, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, getName, arginfo_hs_index_getName, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, getColumn, arginfo_hs_index_getColumn, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, getFilter, arginfo_hs_index_getFilter, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, find, arginfo_hs_index_find, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, insert, arginfo_hs_index_insert, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, update, arginfo_hs_index_update, ZEND_ACC_PUBLIC)
    ZEND_ME(HandlerSocketIndex, remove, arginfo_hs_index_remove, ZEND_ACC_PUBLIC)
    {NULL, NULL, NULL}
};

static void php_hs_free(php_hs_t *hs TSRMLS_DC)
{
    if (hs)
    {
        if (hs->server)
        {
            zval_ptr_dtor(&hs->server);
        }

        zend_object_std_dtor(&hs->object TSRMLS_CC);
        efree(hs);
    }
}

static zend_object_value php_hs_new(zend_class_entry *ce TSRMLS_DC)
{
    zend_object_value retval;
    zval *tmp;
    php_hs_t *hs;

    hs = (php_hs_t *)emalloc(sizeof(php_hs_t));

    zend_object_std_init(&hs->object, ce TSRMLS_CC);
    zend_hash_copy(
        hs->object.properties, &ce->default_properties,
        (copy_ctor_func_t)zval_add_ref, (void *)&tmp, sizeof(zval *));

    retval.handle = zend_objects_store_put(
        hs, (zend_objects_store_dtor_t)zend_objects_destroy_object,
        (zend_objects_free_object_storage_t)php_hs_free,
        NULL TSRMLS_CC);
    retval.handlers = zend_get_std_object_handlers();

    return retval;
}

static void php_hs_index_free(php_hs_index_t *hsi TSRMLS_DC)
{
    if (hsi)
    {
        if (hsi->link)
        {
            zval_ptr_dtor(&hsi->link);
        }

        if (hsi->filter)
        {
            zval_ptr_dtor(&hsi->filter);
        }

        zend_object_std_dtor(&hsi->object TSRMLS_CC);

        efree(hsi);
    }
}

static zend_object_value php_hs_index_new(zend_class_entry *ce TSRMLS_DC)
{
    zend_object_value retval;
    zval *tmp;
    php_hs_index_t *hsi;

    hsi = (php_hs_index_t *)emalloc(sizeof(php_hs_index_t));

    zend_object_std_init(&hsi->object, ce TSRMLS_CC);
    zend_hash_copy(
        hsi->object.properties, &ce->default_properties,
        (copy_ctor_func_t)zval_add_ref, (void *)&tmp, sizeof(zval *));

    retval.handle = zend_objects_store_put(
        hsi, (zend_objects_store_dtor_t)zend_objects_destroy_object,
        (zend_objects_free_object_storage_t)php_hs_index_free,
        NULL TSRMLS_CC);
    retval.handlers = zend_get_std_object_handlers();

    return retval;
}

PHP_MINIT_FUNCTION(handlersocket)
{
    zend_class_entry ce;

    /* HandlerSocket class */
    INIT_CLASS_ENTRY(
        ce, "HandlerSocket", (zend_function_entry *)hs_methods);
    hs_ce = zend_register_internal_class(&ce TSRMLS_CC);
    hs_ce->create_object = php_hs_new;

    /* constant */
    zend_declare_class_constant_string(
        hs_ce, "PRIMARY", strlen("PRIMARY"), HS_PRIMARY TSRMLS_CC);
    /*
    zend_declare_class_constant_string(
        hs_ce, "UPDATE", strlen("UPDATE"), HS_UPDATE TSRMLS_CC);
    zend_declare_class_constant_string(
        hs_ce, "DELETE", strlen("DELETE"), HS_DELETE TSRMLS_CC);
    zend_declare_class_constant_string(
        hs_ce, "FILTER", strlen("FILTER"), HS_FILTER TSRMLS_CC);
    */

    /* HandlerSocketIndex class */
    INIT_CLASS_ENTRY(
        ce, "HandlerSocketIndex", (zend_function_entry *)hs_index_methods);
    hs_index_ce = zend_register_internal_class(&ce TSRMLS_CC);
    hs_index_ce->create_object = php_hs_index_new;

    /* property */
    zend_declare_property_null(
        hs_index_ce, "_id", strlen("_id"),
        ZEND_ACC_PROTECTED TSRMLS_CC);
    zend_declare_property_null(
        hs_index_ce, "_db", strlen("_db"),
        ZEND_ACC_PROTECTED TSRMLS_CC);
    zend_declare_property_null(
        hs_index_ce, "_table", strlen("_table"),
        ZEND_ACC_PROTECTED TSRMLS_CC);
    zend_declare_property_null(
        hs_index_ce, "_name", strlen("_name"),
        ZEND_ACC_PROTECTED TSRMLS_CC);
    zend_declare_property_null(
        hs_index_ce, "_column", strlen("_column"),
        ZEND_ACC_PROTECTED TSRMLS_CC);
    /*
    zend_declare_property_null(
        hs_index_ce, "_filter", strlen("_filter"),
        ZEND_ACC_PROTECTED TSRMLS_CC);
    zend_declare_property_null(
        hs_index_ce, "_while", strlen("_while"),
        ZEND_ACC_PROTECTED TSRMLS_CC);
    zend_declare_property_null(
        hs_index_ce, "_in", strlen("_in"),
        ZEND_ACC_PROTECTED TSRMLS_CC);
    */

    /* HandlerSocketException class */
    INIT_CLASS_ENTRY(ce, "HandlerSocketException", NULL);
    hs_exception_ce = zend_register_internal_class_ex(
        &ce, (zend_class_entry*)zend_exception_get_default(TSRMLS_C),
        NULL TSRMLS_CC);

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

static int hs_stream_connect(php_hs_t *hs)
{
    char *hashkey = NULL;
    char *errstr = NULL;
    int err;
    int ret = -1;

    struct timeval timeout;

    if (hs)
    {
        if (hs->stream != NULL)
        {
            ret = 0;
        }
        else
        {
            if (hs->timeout > 0)
            {
                unsigned long conv;
                conv = (unsigned long)(hs->timeout * 1000000.0);
                timeout.tv_sec = conv / 1000000;
                timeout.tv_usec = conv % 1000000;
            }

            hs->stream = php_stream_xport_create(
                Z_STRVAL_P(hs->server), Z_STRLEN_P(hs->server),
                ENFORCE_SAFE_MODE | REPORT_ERRORS,
                STREAM_XPORT_CLIENT | STREAM_XPORT_CONNECT,
                hashkey, &timeout, NULL, &errstr, &err);

            if (hs->stream == NULL)
            {
                zend_throw_exception_ex(
                    hs_exception_ce, 0 TSRMLS_CC,
                    "unable to connect to %s %s",
                    Z_STRVAL_P(hs->server),
                    errstr == NULL ? "Unknown error" : errstr);
            }
            else
            {
                ret = 0;
            }

            if (hashkey)
            {
                efree(hashkey);
            }

            if (errstr)
            {
                efree(errstr);
            }
        }
    }

    return ret;
}

static void hs_stream_disconnect(php_hs_t *hs)
{
    if (hs)
    {
        if (hs->stream != NULL)
        {
            php_stream_close(hs->stream);
        }
        hs->stream = NULL;
    }
}

/* HandlerSocket Class */
static ZEND_METHOD(HandlerSocket, __construct)
{
    char *host, *port, *server = NULL;
    int host_len, port_len, server_len;
    zval *options = NULL;

    php_hs_t *hs;
    hs = (php_hs_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    //初期化 別の場所の方がよい ?
    MAKE_STD_ZVAL(hs->server);
    hs->stream = NULL;
    hs->timeout = FG(default_socket_timeout);

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "ss|a",
            &host, &host_len, &port, &port_len, &options) == FAILURE)
    {
        return;
    }

    if (strlen(host) == 0 || strlen(port) == 0)
    {
        zend_throw_exception(
            hs_exception_ce, "no server name or port given", 0 TSRMLS_CC);
        return;
    }

    if (options)
    {
        if (Z_TYPE_P(options) == IS_ARRAY)
        {
            zval **timeout;

            if (zend_hash_find(
                    Z_ARRVAL_P(options), "timeout", strlen("timeout") + 1,
                    (void **)&timeout) == SUCCESS)
            {
                hs->timeout = Z_LVAL_PP(timeout);
            }
        }

        //nonblock
        //?
    }

    server_len = spprintf(&server, 0, "%s:%s", host, port);
    ZVAL_STRINGL(hs->server, server, server_len, 1);
    efree(server);

    if (hs_stream_connect(hs) < 0)
    {
        if (!EG(exception))
        {
            zend_throw_exception_ex(
                hs_exception_ce, 0 TSRMLS_CC,
                "unable to connect %s:%s",
                host, port);
        }
        RETURN_FALSE;
    }

    //nonblock
    /*
    int block = 0;
    if (php_stream_set_option(
            hs->stream, PHP_STREAM_OPTION_BLOCKING, block, NULL) == -1)
    {
        zend_error(
            E_WARNING,
            "[handlersocket] (%s) Un set non-blocking mode on a stream",
            __FUNCTION__);
    }
    */

    //zend_update_property_stringl(mongo_ce_Mongo, getThis(), "server", strlen("server"), server, server_len TSRMLS_CC);

/*
//timeout
//nonblocking
if (args.timeout != 0 && !args.nonblocking) {
    struct timeval tv = { };
    tv.tv_sec = args.timeout;
    tv.tv_usec = 0;
    if (setsockopt(fd.get(), SOL_SOCKET, SO_RCVTIMEO, &tv, sizeof(tv)) != 0) {
      return errno_string("setsockopt SO_RCVTIMEO", errno, err_r);
    }
    tv.tv_sec = args.timeout;
    tv.tv_usec = 0;
    if (setsockopt(fd.get(), SOL_SOCKET, SO_SNDTIMEO, &tv, sizeof(tv)) != 0) {
      return errno_string("setsockopt SO_RCVTIMEO", errno, err_r);
    }
  }
  if (args.nonblocking && fcntl(fd.get(), F_SETFL, O_NONBLOCK) != 0) {
    return errno_string("fcntl O_NONBLOCK", errno, err_r);
  }
*/


    //RETURN_NULL();
}

static ZEND_METHOD(HandlerSocket, __destruct)
{
    php_hs_t *hs;
    hs = (php_hs_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    hs_stream_disconnect(hs);
}

static ZEND_METHOD(HandlerSocket, openIndex)
{
    long id;
    char *db, *table, *index, *column;
    int db_len, table_len, index_len, column_len;
    zval *options = NULL;
    zval temp;
    zval *id_z, *db_z, *table_z, *index_z, *column_z;

    php_hs_t *hs;
    hs = (php_hs_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "lssss|a",
            &id, &db, &db_len, &table, &table_len,
            &index, &index_len, &column, &column_len, &options) == FAILURE)
    {
        return;
    }

    MAKE_STD_ZVAL(id_z);
    MAKE_STD_ZVAL(db_z);
    MAKE_STD_ZVAL(table_z);
    MAKE_STD_ZVAL(index_z);
    MAKE_STD_ZVAL(column_z);

    ZVAL_LONG(id_z, id);
    ZVAL_STRINGL(db_z, db, db_len, 1);
    ZVAL_STRINGL(table_z, table, table_len, 1);
    ZVAL_STRINGL(index_z, index, index_len, 1);
    ZVAL_STRINGL(column_z, column, column_len, 1);

    object_init_ex(return_value, hs_index_ce);

    if (options == NULL)
    {
        HS_METHOD6(
            HandlerSocketIndex, __construct, &temp, return_value,
            getThis(), id_z, db_z, table_z, index_z, column_z);
    }
    else
    {
        HS_METHOD7(
            HandlerSocketIndex, __construct, &temp, return_value,
            getThis(), id_z, db_z, table_z, index_z, column_z, options);
    }

    zval_ptr_dtor(&id_z);
    zval_ptr_dtor(&db_z);
    zval_ptr_dtor(&table_z);
    zval_ptr_dtor(&index_z);
    zval_ptr_dtor(&column_z);
}

static zval* array_search_key(zval *value, zval *array)
{
    zval *return_value, **entry, res;
    HashPosition pos;
    ulong index;
    uint key_len;
    char *key;
    int (*is_equal_func)(zval *, zval *, zval * TSRMLS_DC) = is_equal_function;

    MAKE_STD_ZVAL(return_value);

    zend_hash_internal_pointer_reset_ex(HASH_OF(array), &pos);
    while (zend_hash_get_current_data_ex(
               HASH_OF(array), (void **)&entry, &pos) == SUCCESS)
    {
        is_equal_func(&res, value, *entry TSRMLS_CC);
        if (Z_LVAL(res))
        {
            switch (zend_hash_get_current_key_ex(
                        HASH_OF(array), &key, &key_len, &index, 0, &pos))
            {
                case HASH_KEY_IS_STRING:
                    ZVAL_STRINGL(return_value, key, key_len - 1, 1);
                    break;
                case HASH_KEY_IS_LONG:
                    ZVAL_LONG(return_value, index);
                    break;
                default:
                    ZVAL_NULL(return_value);
                    break;
            }

            return return_value;
        }
        zend_hash_move_forward_ex(HASH_OF(array), &pos);
    }

    ZVAL_NULL(return_value);

    return return_value;
}

static void hs_request_string(smart_str *buf, char *str, long str_len)
{
    long i;

    if (str_len <= 0)
    {
        //smart_str_appendc(buf, (unsigned long)0);
        smart_str_appendc(buf, 0x0);
    }
    else
    {
        for (i = 0; i < str_len; i++)
        {
            if ((unsigned char)str[i] > 0x10)
            {
                smart_str_appendc(buf, str[i]);
            }
            else
            {
                //smart_str_appendc(buf, (unsigned long)1);
                //smart_str_appendc(buf, (unsigned long)str[i] + 40);
                smart_str_appendc(buf, 0x1);
                smart_str_appendc(buf, (unsigned char)str[i] + 0x40);
            }
        }
    }

/*
・文字列トークンは、0バイト以上の文字列であらわされる。ただし0x10未満の文字
  については0x01を前置し、0x40を加えたコードであらわされる。それ以外の文字は
  その文字自身のコードであらわされる。
*/
    /*
    while (start != finish)
    {
        const unsigned char c = *start;
        //if (c >= special_char_noescape_min)
        if (c >= 0x10)
        {
            wp[0] = c; // no need to escape
        }
        else
        {
            //wp[0] = special_char_escape_prefix;
            wp[0] = 0x01;
            ++wp;
            //wp[0] = c + special_char_escape_shift;
            wp[0] = c + 0x40;
        }
        ++start;
        ++wp;
    }
    */
}

static void hs_request_long(smart_str *buf, long num)
{
    smart_str_append_long(buf, num);
}

static void hs_request_delim(smart_str *buf)
{
    //smart_str_append_unsigned(buf, 0x09);
    //smart_str_appendc(buf, (unsigned long)9);
    smart_str_appendc(buf, 0x09);
}

static void hs_request_next(smart_str *buf)
{
    //smart_str_append_unsigned(buf, 0x0a);
    //smart_str_appendc(buf, (unsigned long)10);
    smart_str_appendc(buf, 0x0a);
}

static void hs_request_zval_scalar(smart_str *buf, zval *val, int delim)
{
    switch (Z_TYPE_P(val))
    {
        case IS_LONG:
            hs_request_long(buf, Z_LVAL_P(val));
            break;
        case IS_STRING:
            hs_request_string(buf, Z_STRVAL_P(val), Z_STRLEN_P(val));
            break;
        case IS_DOUBLE:
            convert_to_string(val);
            hs_request_string(buf, Z_STRVAL_P(val), Z_STRLEN_P(val));
            break;
        case IS_BOOL:
            convert_to_long(val);
            hs_request_long(buf, Z_LVAL_P(val));
        default:
            //IS_NULL
            //IS_ARRAY
            //IS_OBJECT
            //IS_RESOURCE
            hs_request_long(buf, 0);
            break;
    }

    if (delim > 0)
    {
        hs_request_delim(buf);
    }
}

static void hs_request_zval_array(smart_str *buf, zval *val, int num, int i)
{
    long n;
    HashPosition pos;
    zval **data;

    n = zend_hash_num_elements(HASH_OF(val));
    if (i > 0 && i < n)
    {
        n = i;
    }

    if (num == 1)
    {
        hs_request_long(buf, n);
        hs_request_delim(buf);
    }

    for (zend_hash_internal_pointer_reset_ex(HASH_OF(val), &pos);
         zend_hash_get_current_data_ex(
             HASH_OF(val), (void**) &data, &pos) == SUCCESS;
         zend_hash_move_forward_ex(HASH_OF(val), &pos))
    {
        if (n < 0)
        {
            break;
        }

        n--;
        hs_request_zval_scalar(buf, *data, n);
    }
}

static void hs_request_filter(
    smart_str *buf, zval* val, zval *filter, char *type, int recursive)
{
    long n, i = 0;
    HashPosition pos;
    zval **tmp;

    n = zend_hash_num_elements(HASH_OF(val));
    if (n <= 0)
    {
        return;
    }

    zend_hash_internal_pointer_reset_ex(HASH_OF(val), &pos);
    while (zend_hash_get_current_data_ex(
               HASH_OF(val), (void **)&tmp, &pos) == SUCCESS)
    {
        if (Z_TYPE_PP(tmp) == IS_ARRAY && recursive == 1)
        {
            hs_request_filter(buf, *tmp, filter, type, 0);
        }
        else
        {
            //<ftyp> <fop> <fcol> <fval>

            recursive = 0;

            if (i == 0)
            {
                hs_request_delim(buf);
                hs_request_string(buf, type, strlen(type));
            }

            hs_request_delim(buf);

            if (i == 1)
            {
                zval *index;
                index = array_search_key(*tmp, filter);
                convert_to_string(index);
                hs_request_string(buf, Z_STRVAL_P(index), Z_STRLEN_P(index));
                zval_ptr_dtor(&index);
            }
            else
            {
                convert_to_string(*tmp);
                hs_request_string(buf, Z_STRVAL_PP(tmp), Z_STRLEN_PP(tmp));
            }
        }

        i++;
        zend_hash_move_forward_ex(HASH_OF(val), &pos);
    }

    if (recursive == 0 && n < 3)
    {
        for (n = i; n > 0; n--)
        {
            hs_request_delim(buf);
            hs_request_long(buf, 0);
        }
    }
}

static void hs_request_find(
    smart_str *buf, zval *id, zval *query, zval *filter, zval *options,
    long limit, long offset, int range)
{
    hs_request_long(buf, Z_LVAL_P(id));
    hs_request_delim(buf);

    if (Z_TYPE_P(query) == IS_ARRAY)
    {
        long n;
        n = zend_hash_num_elements(HASH_OF(query));

        HashPosition pos;

        int key_type;
        char *key;
        ulong key_index;
        zval **val;
        uint key_len;
        int op = 0;

        zend_hash_internal_pointer_reset_ex(HASH_OF(query), &pos);
        while (zend_hash_get_current_data_ex(
                   HASH_OF(query), (void **)&val, &pos) == SUCCESS)
        {
            key_type = zend_hash_get_current_key_ex(
                HASH_OF(query), &key, &key_len, &key_index, 0, &pos);

            if (key_type == HASH_KEY_NON_EXISTANT ||
                key_type == HASH_KEY_IS_LONG)
            {
                //default
                if (op == 0)
                {
                    hs_request_string(buf, HS_FIND_EQUAL, 1);
                    op = 1;
                }
            }
            else
            {
                //key check
                /*
                  HS_EQUAL         "="
                  HS_LESS          "<"
                  HS_LESS_EQUAL    "<="
                  HS_GREATER       ">"
                  HS_GREATER_EQUAL ">="
                */
                hs_request_string(buf, key, key_len - 1);
            }

            hs_request_delim(buf);

            if (Z_TYPE_PP(val) == IS_ARRAY)
            {
                hs_request_zval_array(buf, *val, 1, -1);
            }
            else
            {
                hs_request_long(buf, 1);
                hs_request_delim(buf);

                hs_request_zval_scalar(buf, *val, 0);
            }

            zend_hash_move_forward_ex(HASH_OF(query), &pos);
        }
    }
    else
    {
        hs_request_string(buf, HS_FIND_EQUAL, 1);
        hs_request_delim(buf);

        hs_request_long(buf, 1);
        hs_request_delim(buf);

        hs_request_zval_scalar(buf, query, 0);
    }

    if (options && Z_TYPE_P(options) == IS_ARRAY)
    {
        hs_request_delim(buf);
        hs_request_long(buf, limit);

        hs_request_delim(buf);
        hs_request_long(buf, offset);

        zval **zv;

        /* in */
        if (zend_hash_find(
                HASH_OF(options), "in", strlen("in") + 1,
                (void **)&zv) == SUCCESS &&
            Z_TYPE_PP(zv) == IS_ARRAY &&
            zend_hash_num_elements(HASH_OF(*zv)) > 1)
        {
            zval **in;

            hs_request_delim(buf);
            hs_request_string(buf, HS_PROTOCOL_IN, 1);

            zend_hash_index_find(HASH_OF(*zv), 0, (void **)&in);

            convert_to_long(*in);

            hs_request_delim(buf);
            hs_request_long(buf, Z_LVAL_PP(in));

            zend_hash_index_find(HASH_OF(*zv), 1, (void **)&in);

            convert_to_array(*in);

            hs_request_delim(buf);
            hs_request_zval_array(buf, *in, 1, -1);
        }

        /* filter */
        if (filter && Z_TYPE_P(filter) == IS_ARRAY)
        {
            if (zend_hash_find(
                    HASH_OF(options), "filter", strlen("filter") + 1,
                    (void **)&zv) == SUCCESS &&
                Z_TYPE_PP(zv) == IS_ARRAY)
            {
                hs_request_filter(
                    buf, *zv, filter, HS_PROTOCOL_FILTER, 1);
            }

            if (zend_hash_find(
                    HASH_OF(options), "while", strlen("while") + 1,
                    (void **)&zv) == SUCCESS &&
                Z_TYPE_PP(zv) == IS_ARRAY)
            {
                hs_request_filter(
                    buf, *zv, filter, HS_PROTOCOL_WHILE, 1);
            }
        }
    }
    else if ((offset > 0 || limit > 1) || range > 0)
    {
        hs_request_delim(buf);
        hs_request_long(buf, limit);

        hs_request_delim(buf);
        hs_request_long(buf, offset);
    }
}

/* HandlerSocket Index Class */
static ZEND_METHOD(HandlerSocketIndex, __construct)
{
    zval *link;
    long id;
    char *db, *table, *index, *column;
    int db_len, table_len, index_len, column_len;
    zval *opts = NULL;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "Olssss|a",
            &link, hs_ce, &id, &db, &db_len, &table, &table_len,
            &index, &index_len, &column, &column_len, &opts) == FAILURE)
    {
        return;
    }

    php_hs_index_t *hsi;
    hsi = (php_hs_index_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    hsi->link = link;
    hsi->filter = NULL;
    zval_add_ref(&hsi->link);

    if (opts)
    {
        zval **zv;

        MAKE_STD_ZVAL(hsi->filter);

        convert_to_array(opts);
        if (zend_hash_find(
                HASH_OF(opts), "filter", strlen("filter") + 1,
                (void **)&zv) == SUCCESS)
        {
            if (Z_TYPE_PP(zv) == IS_ARRAY)
            {
                *(hsi->filter) = **zv;
                zval_copy_ctor(hsi->filter);
            }
            else
            {
                array_init(hsi->filter);

                convert_to_string(*zv);
                add_next_index_stringl(
                    hsi->filter, Z_STRVAL_PP(zv), Z_STRLEN_PP(zv), 1);
            }
        }
    }

    //HS_GET_OBJ(zv);
    php_hs_t *hs;
    hs = (php_hs_t *)zend_object_store_get_object(link TSRMLS_CC);

    if (hs_stream_connect(hs) < 0)
    {
        zend_throw_exception_ex(
            hs_exception_ce, 0 TSRMLS_CC,
            "unable to open index to %ld %s %s %s %s %s",
            id, db, table, index, column);
        RETURN_FALSE;
    }

    smart_str request = {0};

    //P <indexid> <dbname> <tablename> <indexname> <columns> [<fcolumns>]
    //smart_str_append_unsigned(&request, 'P');

    hs_request_string(&request, "P", 1);
    hs_request_delim(&request);

    //id
    hs_request_long(&request, id);
    hs_request_delim(&request);

    //db
    hs_request_string(&request, db, db_len);
    hs_request_delim(&request);

    //table
    hs_request_string(&request, table, table_len);
    hs_request_delim(&request);

    //index
    hs_request_string(&request, index, index_len);
    hs_request_delim(&request);

    //column
    hs_request_string(&request, column, column_len);

    //filter
    if (hsi->filter && Z_TYPE_P(hsi->filter) == IS_ARRAY)
    {
        zval **tmp;
        HashPosition pos;
        long n, i = 0;

        n = zend_hash_num_elements(HASH_OF(hsi->filter));
        if (n >= 0)
        {
            hs_request_delim(&request);

            zend_hash_internal_pointer_reset_ex(HASH_OF(hsi->filter), &pos);
            while (zend_hash_get_current_data_ex(
                       HASH_OF(hsi->filter), (void **)&tmp, &pos) == SUCCESS)
            {
                switch ((*tmp)->type)
                {
                    case IS_STRING:
                        hs_request_string(
                            &request, Z_STRVAL_PP(tmp), Z_STRLEN_PP(tmp));
                        break;
                    case IS_LONG:
                        hs_request_long(&request, Z_LVAL_PP(tmp));
                        break;
                    default:
                        convert_to_string(*tmp);
                        hs_request_string(
                            &request, Z_STRVAL_PP(tmp), Z_STRLEN_PP(tmp));
                        break;
                }

                if (++i != n)
                {
                    hs_request_string(&request, ",", strlen(","));
                }

                zend_hash_move_forward_ex(HASH_OF(hsi->filter), &pos);
            }
        }
    }

    //eol
    hs_request_next(&request);

    //fwrite: request
    int ret;
    ret = php_stream_write(hs->stream, request.c, request.len);
#ifdef HS_DEBUG
    smart_str_0(&request);
    zend_error(
        E_WARNING,
        "[handlersocket] (request) %ld : \"%s\"", request.len, request.c);
#endif
    smart_str_free(&request);

    //fread: response
    smart_str response = {0};
    char *recv;
    long i;

    long block_size = 4096;

    recv = emalloc(block_size + 1);

    //Z_STRVAL_P(return_value) = emalloc(len + 1);
    //Z_STRLEN_P(return_value) = php_stream_read(stream, Z_STRVAL_P(return_value), len);

    /*
    char ch, done, chunk_size[10];
    done = FALSE;
    while (!done)
    {
        int buf_size = 0;
        php_stream_gets(hs->stream, chunk_size, sizeof(chunk_size));
        if (sscanf(chunk_size, "%x", &buf_size) > 0 )
        {

    */
    //less ~/download/php-5.3.6/ext/soap/php_http.c

    /*
    do
    {
        zend_error(
            E_WARNING,
            "[handlersocket] (%s) read", __FUNCTION__);

        i = php_stream_read(hs->stream, recv, block_size);
        if (i > 0)
        {
            smart_str_appendl(&response, recv, i);
        }

        zend_error(
            E_WARNING,
            "[handlersocket] (%s) %ld", __FUNCTION__, i);

    } while (!php_stream_eof(hs->stream));
    */

    while ((i = php_stream_read(hs->stream, recv, block_size)) > 0)
    {
        smart_str_appendl(&response, recv, i);
        //if ((unsigned long)recv[i - 1] == 10)
        if (recv[i-1] == 0x0a)
        {
            //eof
            break;
        }
    }

    efree(recv);

    //成功
    //0 1

    //NG
    //1 1

    /*
    zend_error(
        E_WARNING,
        "[handlersocket] (%s) response > %ld:%s",
        __FUNCTION__, response.len, response.c);
    */

    if (response.len < 2 || response.c[0] != '0')
    {
        smart_str_free(&response);

        hs_stream_disconnect(hs);
        zend_throw_exception_ex(
            hs_exception_ce, 0 TSRMLS_CC,
            "unable to open index to response: %ld %s %s %s %s %s",
            id, db, table, index, column);
        RETURN_FALSE;
    }

    smart_str_free(&response);

    /*
    hs_request_long(&request, id);
    hs_request_string(&request, db, db_len);
    hs_request_string(&request, table, table_len);
    hs_request_string(&request, index, index_len);
    hs_request_string(&request, column, column_len);
    */


    /* property */
    zend_update_property_long(
        hs_index_ce, getThis(), "_id", strlen("_id"),
        id TSRMLS_CC);
    zend_update_property_stringl(
        hs_index_ce, getThis(), "_db", strlen("_db"),
        db, db_len TSRMLS_CC);
    zend_update_property_stringl(
        hs_index_ce, getThis(), "_table", strlen("_table"),
        table, table_len TSRMLS_CC);
    zend_update_property_stringl(
        hs_index_ce, getThis(), "_name", strlen("_name"),
        index, index_len TSRMLS_CC);
    zend_update_property_stringl(
        hs_index_ce, getThis(), "_column", strlen("_column"),
        column, column_len TSRMLS_CC);
    /*
    zend_update_property(
        hs_index_ce, getThis(), "_filter", strlen("_filter"),
        filter TSRMLS_CC);
    */

    /* Eat up '\r' '\n' */
    //ch = php_stream_getc(stream);
    /*
    if (ch == '\r') {
        ch = php_stream_getc(stream);
    }
    if (ch != '\n') {
        // Somthing wrong in chunked encoding
        if (http_buf) {
            efree(http_buf);
        }
        return FALSE;
    }
    */

    /*
    i = php_stream_read(hs->stream, recv, len);
    zend_error(
        E_WARNING,
        "[handlersocket] (%s) 1:%ld", __FUNCTION__, i);
    smart_str_appendl(&response, recv, i);
    */

    /*
    zend_error(
        E_WARNING,
        "[handlersocket] (%s) 1:%ld", __FUNCTION__, recv[i-1]);
    */

    /*
    const char *const nl = memchr_char(lbegin, '\n', lend - lbegin);
    if (nl != 0) {
      offset = (nl + 1) - readbuf.begin();
      break;
    }
    */




/*
インデックスを開く

open_index命令は次のような構文を持つ。

    P <indexid> <dbname> <tablename> <indexname> <columns> [<fcolumns>]

- <indexid>は数字で、同一接続上で後に実行する命令の、対象索引を指定するため
  に使われる。
- <dbname>, <tablename>, <indexname>は文字列で、それぞれDB名、テーブル名、
  索引の名前を指定する。<indexname>として「PRIMARY」を指定するとプライマリ
  キーが開かれる。
- <columns>はカンマ区切りの列名のリスト。
- <fcolumns>はカンマ区切りの列名のリスト。これは省略することができる。

このopen_index命令が実行されると、HandlerSocketプラグインは指定されたDB、
テーブル、索引を開く。開かれた索引は接続が閉じられるまで開かれたままになる。
開かれた索引は<indexid>の数字で識別される。もし既に<indexid>に指定された番号
の索引が既に開かれている場合は古いほうが閉じられる。この<indexid>はなるべく
小さな数字を使ったほうが効率が良い。
*/

/*
・HandlerSocketのプロトコルは行ベース。各行は改行文字(0x0a)で終わる。
・各行は複数のトークンからなり、トークン間はTAB文字(0x09)で区切られる。
・トークンはNULLトークンか、文字列トークンのいずれか。
・NULLトークンは単一のNUL文字(0x00)であらわされる。
・文字列トークンは、0バイト以上の文字列であらわされる。ただし0x10未満の文字
  については0x01を前置し、0x40を加えたコードであらわされる。それ以外の文字は
  その文字自身のコードであらわされる。
*/

/*
リクエストとレスポンス

・HandlerSocketのプロトコルは単純なリクエスト・レスポンスプロトコルになって
  いる。接続が確立した後は、まずクライアントがリクエストを送る。
・サーバは、クライアントが送ったリクエストと丁度同じ数の行(レスポンス)を返
  す。
・リクエストはパイプライン化してよい。つまりクライアントは前に送ったリクエス
  トに対する返事(レスポンス)を待たずに次のリクエストを送ってもよい。
*/
}

static ZEND_METHOD(HandlerSocketIndex, __destruct)
{
    php_hs_index_t *hsi;
    hsi = (php_hs_index_t *)zend_object_store_get_object(getThis() TSRMLS_CC);
}

static ZEND_METHOD(HandlerSocketIndex, getId)
{
    zval *prop;

    prop = zend_read_property(
        hs_index_ce, getThis(), "_id", strlen("_id"), 0 TSRMLS_CC);

    RETVAL_ZVAL(prop, 1, 0);
}
static ZEND_METHOD(HandlerSocketIndex, getDatabase)
{
    zval *prop;

    prop = zend_read_property(
        hs_index_ce, getThis(), "_db", strlen("_db"), 0 TSRMLS_CC);

    RETVAL_ZVAL(prop, 1, 0);
}

static ZEND_METHOD(HandlerSocketIndex, getTable)
{
    zval *prop;

    prop = zend_read_property(
        hs_index_ce, getThis(), "_table", strlen("_table"), 0 TSRMLS_CC);

    RETVAL_ZVAL(prop, 1, 0);
}

static ZEND_METHOD(HandlerSocketIndex, getName)
{
    zval *prop;

    prop = zend_read_property(
        hs_index_ce, getThis(), "_name", strlen("_name"), 0 TSRMLS_CC);

    RETVAL_ZVAL(prop, 1, 0);
}

static ZEND_METHOD(HandlerSocketIndex, getColumn)
{
    zval *prop;

    prop = zend_read_property(
        hs_index_ce, getThis(), "_column", strlen("_column"), 0 TSRMLS_CC);

    /* カンマのパース処理 */
    /* explode */

    RETVAL_ZVAL(prop, 1, 0);
}

static ZEND_METHOD(HandlerSocketIndex, getFilter)
{
    php_hs_index_t *hsi;
    hsi = (php_hs_index_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    if (!hsi->filter)
    {
        RETURN_NULL();
    }

    RETVAL_ZVAL(hsi->filter, 1, 0);
}

static zval* hs_response_add(zval *return_value)
{
    zval *value;
    MAKE_STD_ZVAL(value);
    array_init(value);
    add_next_index_zval(return_value, value);
    return value;
}


/*
static zval* hs_response_item_init()
{
    zval *item;
    MAKE_STD_ZVAL(item);
    //ALLOC_INIT_ZVAL(item);
    array_init(item);

    return item;
}
*/
 /*
static void hs_response_item_zval(zval *item, zval *val)
{
 */
    /*
    if (item == NULL)
    {
        MAKE_STD_ZVAL(item);
        //ALLOC_INIT_ZVAL(item);
        array_init(item);
    }
    */
/*
    add_next_index_zval(item, val);
    return;
}
*/
static zval* hs_response_zval(smart_str *buf)
{
    zval *val;
    MAKE_STD_ZVAL(val);
    ZVAL_STRINGL(val, buf->c, buf->len, 1);
    return val;
}


static ZEND_METHOD(HandlerSocketIndex, find)
{
    zval *query = NULL;
    long limit = 1, offset = 0;
    zval *options = NULL;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "z|lla",
            &query, &limit, &offset, &options) == FAILURE)
    {
        return;
    }

    php_hs_index_t *hsi;
    hsi = (php_hs_index_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    php_hs_t *hs;
    hs = (php_hs_t *)zend_object_store_get_object(hsi->link TSRMLS_CC);

    smart_str request = {0};

    //id
    zval *id;
    id = zend_read_property(
        hs_index_ce, getThis(), "_id", strlen("_id"), 0 TSRMLS_CC);

    if (Z_LVAL_P(id) <= 0)
    {
        //error
        RETURN_NULL();
    }

    hs_request_find(
        &request, id, query, hsi->filter, options, limit, offset, -1);

    //eol
    hs_request_next(&request);

/*
find命令は次のような構文を持つ。

    <indexid> <op> <vlen> <v1> ... <vn> [LIM] [IN] [FILTER ...]

LIMは次のようなパラメータの並び

    <limit> <offset>

INは次のようなパラメータの並び

    @ <icol> <ivlen> <iv1> ... <ivn>

FILTERは次のようなパラメータの並び

    <ftyp> <fop> <fcol> <fval>
*/


    //fwrite: request
    int ret;
    ret = php_stream_write(hs->stream, request.c, request.len);
#ifdef HS_DEBUG
    smart_str_0(&request);
    zend_error(
        E_WARNING,
        "[handlersocket] (request) %ld : \"%s\"", request.len, request.c);
#endif
    smart_str_free(&request);

    //fread: response
    smart_str response = {0};
    char *recv;
    long i, l;

    long c = 0;
    long r = 0, d = 0, n = 0;

    long block_size = 4096;
    int escape = 0;

    zval *val;

    array_init(return_value);
    zval *item = hs_response_add(return_value);

    recv = emalloc(block_size + 1);

    while ((l = php_stream_read(hs->stream, recv, block_size)) > 0)
    {
#ifdef HS_DEBUG
        recv[l] = '\0';
        zend_error(
            E_WARNING,
            "[handlersocket] (recv) %ld : \"%s\"", strlen(recv), recv);
#endif

        for (i = 0; i < l; i++)
        {
            if (recv[i] == 0x09)
            {
                if (c == 0)
                {
                    val = hs_response_zval(&response);
                    convert_to_long(val);
                    r = Z_LVAL_P(val);
                    c++;
                    zval_ptr_dtor(&val);
                }
                else if (c == 1)
                {
                    val = hs_response_zval(&response);
                    convert_to_long(val);
                    d = Z_LVAL_P(val);
                    c++;
                    zval_ptr_dtor(&val);
                }
                else
                {
                    if (response.len == 1 && response.c[0] == 0x00)
                    {
                        add_next_index_unset(item);
                    }
                    else
                    {
                        add_next_index_stringl(
                            item, response.c, response.len, 1);
                    }

                    n++;
                    if (n == d)
                    {
                        item = hs_response_add(return_value);
                        n = 0;
                    }
                }

                smart_str_free(&response);

                continue;
            }
            else if (recv[i] == 0x0a)
            {
                if (response.len == 1 && response.c[0] == 0x00)
                {
                    add_next_index_unset(item);
                }
                else
                {
                    add_next_index_stringl(
                        item, response.c, response.len, 1);
                }
                break;
            }

            if (recv[i] == 0x01)
            {
                escape = 1;
            }
            else if (escape)
            {
                escape = 0;
                smart_str_appendc(&response, (unsigned char)recv[i] - 0x40);
            }
            else
            {
                smart_str_appendc(&response, recv[i]);
            }
        }

        if (recv[i] == 0x0a)
        {
            break;
        }
    }

    efree(recv);

    smart_str_free(&response);

/*
findに対するレスポンス

find命令が成功したとき、レスポンスは次の構文を持つ。

    0 <numcolumns> <r1> ... <rn>

- <numcolumns>はfind命令の対応するopen_index命令に指定した<columns>の長さに
  一致する。
- <r1> ... <rn>は結果セット。もしN行がfind命令で見つかったなら、<r1> ...
  <rn>の長さは ( <numcolumns> * N )になる。
*/
}

static ZEND_METHOD(HandlerSocketIndex, insert)
{
    zval ***args;
    long i, argc = ZEND_NUM_ARGS();

    if (argc < 1)
    {
        WRONG_PARAM_COUNT;
    }

    args = safe_emalloc(argc, sizeof(zval **), 0);
    if (zend_get_parameters_array_ex(argc, args) == FAILURE)
    {
        efree(args);
        WRONG_PARAM_COUNT;
    }

    php_hs_index_t *hsi;
    hsi = (php_hs_index_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    php_hs_t *hs;
    hs = (php_hs_t *)zend_object_store_get_object(hsi->link TSRMLS_CC);


    smart_str request = {0};

    //id
    zval *id;
    id = zend_read_property(
        hs_index_ce, getThis(), "_id", strlen("_id"), 0 TSRMLS_CC);

    if (Z_LVAL_P(id) <= 0)
    {
        efree(args);
        //error
        RETURN_NULL();
    }

    hs_request_long(&request, Z_LVAL_P(id));
    hs_request_delim(&request);

    hs_request_string(&request, HS_PROTOCOL_INSERT, 1);
    hs_request_delim(&request);

    if (Z_TYPE_PP(args[0]) == IS_ARRAY)
    {
        hs_request_zval_array(&request, *args[0], 1, -1);
    }
    else
    {
        hs_request_long(&request, argc);
        hs_request_delim(&request);

        for (i = 0; i  < argc - 1; i++)
        {
            hs_request_zval_scalar(&request, *args[i], 1);
        }

        hs_request_zval_scalar(&request, *args[argc-1], 0);
    }

    efree(args);

    //eol
    hs_request_next(&request);

/*
insert命令は次のような構文を持つ。

    <indexid> + <vlen> <v1> ... <vn>

- <vlen>は後に続くパラメータ<v1> ... <vn>の長さ。これは対応するopen_indexの
  <columns>の長さに等しいか小さくなければならない。
- <v1> ... <vn>はセットされる各列の値。指定されないかった列についてはその列
  のデフォルト値がセットされる。
*/

    //fwrite: request
    int ret;
    ret = php_stream_write(hs->stream, request.c, request.len);
#ifdef HS_DEBUG
    smart_str_0(&request);
    zend_error(
        E_WARNING,
        "[handlersocket] (request) %ld : \"%s\"", request.len, request.c);
#endif
    smart_str_free(&request);

    //fread: response
    smart_str response = {0};
    char *recv;
    //long i;
    long block_size = 4096;

    recv = emalloc(block_size + 1);

    while ((i = php_stream_read(hs->stream, recv, block_size)) > 0)
    {
#ifdef HS_DEBUG
        recv[i] = '\0';
        zend_error(
            E_WARNING,
            "[handlersocket] (recv) %ld : \"%s\"", strlen(recv), recv);
#endif
        smart_str_appendl(&response, recv, i);
        if (recv[i-1] == 0x0a)
        {
            break;
        }
    }

    efree(recv);

    //成功
    //0 1

    if (response.len < 2 || response.c[0] != '0')
    {
        smart_str_free(&response);
        RETURN_FALSE;
    }

    smart_str_free(&response);

    RETURN_TRUE;
}

static ZEND_METHOD(HandlerSocketIndex, update)
{
    zval *values = NULL, *query = NULL, *options = NULL;
    long limit = 1, offset = 0;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "az|lla",
            &values, &query, &limit, &offset, &options) == FAILURE)
    {
        return;
    }

    php_hs_index_t *hsi;
    hsi = (php_hs_index_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    php_hs_t *hs;
    hs = (php_hs_t *)zend_object_store_get_object(hsi->link TSRMLS_CC);

    smart_str request = {0};

    //id
    zval *id;
    id = zend_read_property(
        hs_index_ce, getThis(), "_id", strlen("_id"), 0 TSRMLS_CC);

    if (Z_LVAL_P(id) <= 0)
    {
        //error
        RETURN_NULL();
    }

    hs_request_find(
        &request, id, query, hsi->filter, options, limit, offset, 1);

    /* mod */
    if (Z_TYPE_P(values) == IS_ARRAY)
    {
        HashPosition pos;

        int key_type;
        char *key;
        ulong key_index;
        zval **val;
        uint key_len;

        zend_hash_internal_pointer_reset_ex(HASH_OF(values), &pos);
        if (zend_hash_get_current_data_ex(
                HASH_OF(values), (void **)&val, &pos) == SUCCESS)
        {
            key_type = zend_hash_get_current_key_ex(
                HASH_OF(values), &key, &key_len, &key_index, 0, &pos);

            if (key_type == HASH_KEY_IS_STRING)
            {
                //key check
                /*
                  HS_MODIFY_UPDATE         "U"
                  HS_MODIFY_INCREMENT      "+"
                  HS_MODIFY_DECREMENT      "-"
                  HS_MODIFY_REMOVE         "D"
                  HS_MODIFY_UPDATE_PREV    "U?"
                  HS_MODIFY_INCREMENT_PREV "+?"
                  HS_MODIFY_DECREMENT_PREV "-?"
                  HS_MODIFY_REMOVE_PREV    "D?"
                */

                hs_request_delim(&request);

                hs_request_string(&request, key, key_len - 1);

                hs_request_delim(&request);

                if (Z_TYPE_PP(val) == IS_ARRAY)
                {
                    hs_request_zval_array(&request, *val, 0, -1);
                }
                else
                {
                    hs_request_long(&request, 1);
                    hs_request_delim(&request);

                    hs_request_zval_scalar(&request, *val, 0);
                }
            }
        }
    }

    //eol
    hs_request_next(&request);

    //fwrite: request
    int ret;
    ret = php_stream_write(hs->stream, request.c, request.len);
#ifdef HS_DEBUG
    smart_str_0(&request);
    zend_error(
        E_WARNING,
        "[handlersocket] (request) %ld : \"%s\"", request.len, request.c);
#endif
    smart_str_free(&request);

    //fread: response
    smart_str response = {0};
    char *recv;
    long i, l;

    long c = 0;
    long r = 0, d = 0, n = 0;

    long block_size = 4096;
    int escape = 0;

    zval *val;

    array_init(return_value);
    zval *item = hs_response_add(return_value);

    recv = emalloc(block_size + 1);

    while ((l = php_stream_read(hs->stream, recv, block_size)) > 0)
    {
#ifdef HS_DEBUG
        recv[l] = '\0';
        zend_error(
            E_WARNING,
            "[handlersocket] (recv) %ld : \"%s\"", strlen(recv), recv);
#endif
        for (i = 0; i < l; i++)
        {
            if (recv[i] == 0x09)
            {
                if (c == 0)
                {
                    val = hs_response_zval(&response);
                    convert_to_long(val);
                    r = Z_LVAL_P(val);
                    c++;
                    zval_ptr_dtor(&val);
                }
                else if (c == 1)
                {
                    val = hs_response_zval(&response);
                    convert_to_long(val);
                    d = Z_LVAL_P(val);
                    c++;
                    zval_ptr_dtor(&val);
                }
                else
                {
                    if (response.len == 1 && response.c[0] == 0x00)
                    {
                        add_next_index_unset(item);
                    }
                    else
                    {
                        add_next_index_stringl(
                            item, response.c, response.len, 1);
                    }

                    n++;
                    if (n == d)
                    {
                        item = hs_response_add(return_value);
                        n = 0;
                    }
                }

                smart_str_free(&response);

                continue;
            }
            else if (recv[i] == 0x0a)
            {
                if (response.len == 1 && response.c[0] == 0x00)
                {
                    add_next_index_unset(item);
                }
                else
                {
                    add_next_index_stringl(
                        item, response.c, response.len, 1);
                }
                break;
            }

            if (recv[i] == 0x01)
            {
                escape = 1;
            }
            else if (escape)
            {
                escape = 0;
                smart_str_appendc(&response, (unsigned char)recv[i] - 0x40);
            }
            else
            {
                smart_str_appendc(&response, recv[i]);
            }
        }

        if (recv[i] == 0x0a)
        {
            break;
        }
    }

    efree(recv);

    smart_str_free(&response);
}
/*
find_modifyに対するレスポンス

find_modify命令が成功したとき、レスポンスは次の構文を持つ。

    0 1 <nummod>

- <nummod>は変更された行の数。
- 例外として、<mop>が'?'の付いたものであった場合には、find命令に対するレスポ
  ンスと同じ構文のレスポンスを返す。
*/

static ZEND_METHOD(HandlerSocketIndex, remove)
{
    zval *query = NULL, *options = NULL;
    long limit = 1, offset = 0;

    if (zend_parse_parameters(
            ZEND_NUM_ARGS() TSRMLS_CC, "z|lla",
            &query, &limit, &offset, &options) == FAILURE)
    {
        return;
    }

    php_hs_index_t *hsi;
    hsi = (php_hs_index_t *)zend_object_store_get_object(getThis() TSRMLS_CC);

    php_hs_t *hs;
    hs = (php_hs_t *)zend_object_store_get_object(hsi->link TSRMLS_CC);

    smart_str request = {0};

    //id
    zval *id;
    id = zend_read_property(
        hs_index_ce, getThis(), "_id", strlen("_id"), 0 TSRMLS_CC);

    if (Z_LVAL_P(id) <= 0)
    {
        //error
        RETURN_NULL();
    }

    hs_request_find(
        &request, id, query, hsi->filter, options, limit, offset, 1);

    /* mod: D */
    //key check
    /*
      HS_MODIFY_REMOVE         "D"
      HS_MODIFY_REMOVE_PREV    "D?"
    */

    hs_request_delim(&request);

    hs_request_string(&request, HS_MODIFY_REMOVE, 1);

    //eol
    hs_request_next(&request);

    //fwrite: request
    int ret;
    ret = php_stream_write(hs->stream, request.c, request.len);
#ifdef HS_DEBUG
    smart_str_0(&request);
    zend_error(
        E_WARNING,
        "[handlersocket] (request) %ld : \"%s\"", request.len, request.c);
#endif
    smart_str_free(&request);

    //fread: response
    smart_str response = {0};
    char *recv;
    long i, l;

    long c = 0;
    long r = 0, d = 0, n = 0;

    long block_size = 4096;
    int escape = 0;

    zval *val;

    array_init(return_value);
    zval *item = hs_response_add(return_value);

    recv = emalloc(block_size + 1);

    while ((l = php_stream_read(hs->stream, recv, block_size)) > 0)
    {
#ifdef HS_DEBUG
        recv[l] = '\0';
        zend_error(
            E_WARNING,
            "[handlersocket] (recv) %ld : \"%s\"", strlen(recv), recv);
#endif
        for (i = 0; i < l; i++)
        {
            if (recv[i] == 0x09)
            {
                if (c == 0)
                {
                    val = hs_response_zval(&response);
                    convert_to_long(val);
                    r = Z_LVAL_P(val);
                    c++;
                    zval_ptr_dtor(&val);
                }
                else if (c == 1)
                {
                    val = hs_response_zval(&response);
                    convert_to_long(val);
                    d = Z_LVAL_P(val);
                    c++;
                    zval_ptr_dtor(&val);
                }
                else
                {
                    if (response.len == 1 && response.c[0] == 0x00)
                    {
                        add_next_index_unset(item);
                    }
                    else
                    {
                        add_next_index_stringl(
                            item, response.c, response.len, 1);
                    }

                    n++;
                    if (n == d)
                    {
                        item = hs_response_add(return_value);
                        n = 0;
                    }
                }

                smart_str_free(&response);

                continue;
            }
            else if (recv[i] == 0x0a)
            {
                if (response.len == 1 && response.c[0] == 0x00)
                {
                    add_next_index_unset(item);
                }
                else
                {
                    add_next_index_stringl(
                        item, response.c, response.len, 1);
                }
                break;
            }

            if (recv[i] == 0x01)
            {
                escape = 1;
            }
            else if (escape)
            {
                escape = 0;
                smart_str_appendc(&response, (unsigned char)recv[i] - 0x40);
            }
            else
            {
                smart_str_appendc(&response, recv[i]);
            }
        }

        if (recv[i] == 0x0a)
        {
            break;
        }
    }

    efree(recv);

    smart_str_free(&response);
}
