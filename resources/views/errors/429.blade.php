{{--
    Demasiadas peticiones: lo devuelve el rate limiting del login
    (AppServiceProvider::configurarLimiteDeLogin) y el de Breeze.
--}}
@extends('errors.layout')

@section('codigo', '429')
@section('titulo', 'Demasiados intentos')
@section('mensaje', 'Has hecho demasiadas peticiones en poco tiempo. Espera un momento e inténtalo de nuevo.')
