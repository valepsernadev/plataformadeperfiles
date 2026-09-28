{{--
    Error interno. Con APP_DEBUG=false el usuario ve SOLO esto: ni stack trace,
    ni ruta del archivo, ni consulta SQL, ni estructura de la base de datos
    (guía, secciones 5 y 7). El detalle técnico queda en storage/logs.
--}}
@extends('errors.layout')

@section('codigo', '500')
@section('titulo', 'Algo salió mal')
@section('mensaje', 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.')
