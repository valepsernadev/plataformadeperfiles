{{--
    Token CSRF ausente o caducado. Es la respuesta que ve un atacante que
    intenta enviar un formulario desde otro sitio (guía, secciones 4 y 7).
--}}
@extends('errors.layout')

@section('codigo', '419')
@section('titulo', 'Sesión expirada')
@section('mensaje', 'Tu sesión expiró o la petición no pudo verificarse. Vuelve a iniciar sesión e inténtalo de nuevo.')
