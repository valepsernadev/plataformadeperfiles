{{--
    Página de error para `abort(403)`, que es lo que devuelven el middleware
    `admin` (EnsureUserIsAdmin) y las policies cuando alguien intenta acceder a
    un recurso que no le corresponde. El mensaje es deliberadamente genérico:
    no confirma si el recurso existe ni por qué se denegó.
--}}
@extends('errors.layout')

@section('codigo', '403')
@section('titulo', 'Acceso denegado')
@section('mensaje', 'No tienes permisos para acceder a este recurso.')
