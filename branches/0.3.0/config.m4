dnl config.m4 for extension handlersocket

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl Check PHP version:

AC_MSG_CHECKING([PHP version])
tmp_version=$PHP_VERSION
if test -z "$tmp_version"; then
  if test -z "$PHP_CONFIG"; then
    AC_MSG_ERROR([php-config not found])
  fi
  php_version=`$PHP_CONFIG --version 2> /dev/null | head -n 1 | sed -e 's#\([0-9]\.[0-9]*\.[0-9]*\)\(.*\)#\1#'`
else
  php_version=`echo "$VERSION" | sed -e 's#\([0-9]\.[0-9]*\.[0-9]*\)\(.*\)#\1#'`
fi

if test -z "$php_version"; then
  AC_MSG_ERROR([failed to detect PHP version, please report])
fi

ac_IFS=$IFS
IFS="."
set $php_version
IFS=$ac_IFS
hs_php_version=`expr [$]1 \* 1000000 + [$]2 \* 1000 + [$]3`

if test "$hs_php_version" -le "5000000"; then
  AC_MSG_ERROR([You need at least PHP 5.0.0 to be able to use this version of HandlerSocket. PHP $php_version found])
else
  AC_MSG_RESULT([$php_version, ok])
fi

dnl If your extension references something external, use with:

PHP_ARG_WITH(handlersocket, for handlersocket support,
Make sure that the comment is aligned:
[  --with-handlersocket                 Include handlersocket support])

if test "$PHP_HANDLERSOCKET" != "no"; then
  ifdef([PHP_INSTALL_HEADERS],
  [
    PHP_INSTALL_HEADERS([ext/handlersocket], [php_handlersocket.h])
  ], [
    PHP_ADD_MAKEFILE_FRAGMENT
  ])

  PHP_NEW_EXTENSION(handlersocket, handlersocket.c, $ext_shared)
fi
