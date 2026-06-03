#!/bin/bash
# Script de despliegue en VPS para FUTRACK

echo "====================================="
echo " FUTRACK - Despliegue en VPS (Docker)"
echo "====================================="

# Verificar si docker y docker-compose están instalados
if ! command -v docker &> /dev/null
then
    echo "Docker no encontrado. Por favor instala Docker primero."
    exit 1
fi

if ! command -v docker-compose &> /dev/null
then
    echo "Docker Compose no encontrado. Por favor instala Docker Compose primero."
    exit 1
fi

echo "Iniciando contenedores (Web/PHP y Base de Datos MySQL)..."
docker-compose down
docker-compose up -d --build

echo ""
echo "Los contenedores están corriendo. Puedes revisar los logs con:"
echo "docker-compose logs -f"
echo ""
echo "Recuerda configurar tu dominio/subdominio y apuntarlo a la IP de este VPS."
echo "Para HTTPS, es altamente recomendado usar Nginx Ingress o Nginx Proxy Manager en el VPS."
