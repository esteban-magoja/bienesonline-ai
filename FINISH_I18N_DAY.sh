#!/bin/bash
# Script para finalizar el trabajo diario de i18n

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=====================================${NC}"
echo -e "${BLUE}   i18n Implementation - Daily End${NC}"
echo -e "${BLUE}=====================================${NC}\n"

# Leer día actual
CURRENT_DAY=$(grep "^CURRENT_DAY=" .i18n-progress | cut -d'=' -f2)

if [ "$CURRENT_DAY" -eq 0 ]; then
    echo -e "${RED}No hay día activo. Usa ./START_I18N.sh primero.${NC}\n"
    exit 1
fi

echo -e "${YELLOW}Finalizando Día ${CURRENT_DAY}/12${NC}\n"

# Testing básico
echo -e "${BLUE}Ejecutando tests básicos...${NC}"
php artisan test --filter=Localization 2>/dev/null

# Status de git
echo -e "\n${BLUE}Estado de cambios:${NC}\n"
git status --short

# Preguntar si completó el día
echo -e "\n${GREEN}¿Completaste todas las tareas del Día ${CURRENT_DAY}? (y/n)${NC}"
read -r response

if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
    STATUS="completed"
    echo -e "${GREEN}✓ Marcando día como completado${NC}"
else
    echo -e "${YELLOW}¿Cuánto completaste? (in_progress/blocked)${NC}"
    read -r STATUS
fi

# Pedir horas trabajadas
echo -e "\n${YELLOW}¿Cuántas horas trabajaste hoy?${NC}"
read -r HOURS

# Pedir notas
echo -e "\n${YELLOW}Notas del día (opcional, presiona Enter para omitir):${NC}"
read -r NOTES

# Actualizar archivo de progreso
sed -i "s/^DAY_${CURRENT_DAY}_STATUS=.*/DAY_${CURRENT_DAY}_STATUS=${STATUS}/" .i18n-progress
sed -i "s/^DAY_${CURRENT_DAY}_HOURS=.*/DAY_${CURRENT_DAY}_HOURS=${HOURS}/" .i18n-progress

if [ -n "$NOTES" ]; then
    sed -i "s/^DAY_${CURRENT_DAY}_NOTES=.*/DAY_${CURRENT_DAY}_NOTES=\"${NOTES}\"/" .i18n-progress
fi

# Commit de cambios
echo -e "\n${BLUE}Haciendo commit de los cambios...${NC}"
git add .
git commit -m "[Day ${CURRENT_DAY}] ${STATUS^}: Finalizando día ${CURRENT_DAY}

Horas: ${HOURS}h
Notas: ${NOTES}"

# Push
echo -e "\n${YELLOW}¿Hacer push al repositorio remoto? (y/n)${NC}"
read -r push_response

if [[ "$push_response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
    BRANCH_NAME="i18n/day-$(printf %02d $CURRENT_DAY)"
    git push origin "$BRANCH_NAME"
    echo -e "${GREEN}✓ Push completado${NC}"
fi

# Avanzar al siguiente día si completó
if [ "$STATUS" = "completed" ]; then
    NEXT_DAY=$((CURRENT_DAY + 1))
    if [ "$NEXT_DAY" -le 12 ]; then
        sed -i "s/^CURRENT_DAY=.*/CURRENT_DAY=${NEXT_DAY}/" .i18n-progress
        echo -e "\n${GREEN}✓ Día ${CURRENT_DAY} completado!${NC}"
        echo -e "${BLUE}Siguiente: Día ${NEXT_DAY}/12${NC}"
    else
        sed -i "s/^CURRENT_STATUS=.*/CURRENT_STATUS=completed/" .i18n-progress
        echo -e "\n${GREEN}🎉 ¡FELICITACIONES! Has completado todo el plan i18n! 🎉${NC}\n"
    fi
fi

# Resumen
echo -e "\n${BLUE}=====================================${NC}"
echo -e "${BLUE}   Resumen del Día ${CURRENT_DAY}${NC}"
echo -e "${BLUE}=====================================${NC}"
echo -e "Estado: ${STATUS}"
echo -e "Horas: ${HOURS}h"
echo -e "Notas: ${NOTES}"
echo -e "\n${GREEN}¡Buen trabajo! 👏${NC}\n"
