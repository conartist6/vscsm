#                                               -*- Autoconf -*-
# Process this file with autoconf to produce a configure script.

AC_PREREQ([2.63])
AC_INIT(vscsm, 0.0.3, conartist6@gmail.com)
AM_INIT_AUTOMAKE(vscsm, 0.0.3)
AC_CONFIG_SRCDIR([src/vscsm.c])
AC_CONFIG_HEADERS([vscsm.h])

# Checks for programs.
AC_PROG_CC

# Checks for libraries.
AC_CHECK_LIB([config], [config_setting_remove], [], [
    echo "The vscsm requires libconfig."
    exit -1])
AC_CHECK_LIB([pthread], [pthread_create], [], [
    echo "The vscsm requires pthreads!"
    exit -1])

# Checks for header files.
AC_CHECK_HEADERS([fcntl.h stdlib.h string.h unistd.h libconfig.h pthread.h])

# Checks for typedefs, structures, and compiler characteristics.
AC_TYPE_PID_T
AC_TYPE_SIZE_T
AC_TYPE_SSIZE_T

# Checks for library functions.
AC_FUNC_FORK
AC_FUNC_MALLOC
AC_FUNC_REALLOC
AC_CHECK_FUNCS([dup2 strchr strerror])

AC_OUTPUT(Makefile src/Makefile)
