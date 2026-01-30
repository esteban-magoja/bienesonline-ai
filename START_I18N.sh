#!/bin/bash
# Script para iniciar el trabajo diario de i18n

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}=====================================${NC}"
echo -e "${BLUE}   i18n Implementation - Daily Start${NC}"
echo -e "${BLUE}=====================================${NC}\n"

# Leer día actual del archivo de progreso
CURRENT_DAY=$(grep "^CURRENT_DAY=" .i18n-progress | cut -d'=' -f2)

echo -e "${YELLOW}Día actual: ${CURRENT_DAY}/12${NC}\n"

if [ "$CURRENT_DAY" -eq 0 ]; then
    echo -e "${GREEN}¿Quieres empezar el Día 1? (y/n)${NC}"
    read -r response
    if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
        CURRENT_DAY=1
        sed -i "s/^CURRENT_DAY=.*/CURRENT_DAY=1/" .i18n-progress
        sed -i "s/^CURRENT_STATUS=.*/CURRENT_STATUS=in_progress/" .i18n-progress
        sed -i "s/^START_DATE=.*/START_DATE=$(date +%Y-%m-%d)/" .i18n-progress
        sed -i "s/^DAY_1_STATUS=.*/DAY_1_STATUS=in_progress/" .i18n-progress
        sed -i "s/^DAY_1_DATE=.*/DAY_1_DATE=$(date +%Y-%m-%d)/" .i18n-progress
    fi
fi

if [ "$CURRENT_DAY" -gt 0 ] && [ "$CURRENT_DAY" -le 12 ]; then
    echo -e "${GREEN}📋 Abriendo documentación del Día ${CURRENT_DAY}...${NC}\n"
    
    # Crear branch si no existe
    BRANCH_NAME="i18n/day-$(printf %02d $CURRENT_DAY)"
    CURRENT_BRANCH=$(git branch --show-current)
    
    if [ "$CURRENT_BRANCH" != "$BRANCH_NAME" ]; then
        echo -e "${YELLOW}Creando/cambiando a branch: ${BRANCH_NAME}${NC}"
        git checkout -b "$BRANCH_NAME" 2>/dev/null || git checkout "$BRANCH_NAME"
    fi
    
    echo -e "\n${GREEN}✓ Branch activo: ${BRANCH_NAME}${NC}"
    echo -e "${GREEN}✓ Estado del repositorio:${NC}\n"
    git status --short
    
    echo -e "\n${BLUE}=====================================${NC}"
    echo -e "${BLUE}   Pasos a seguir:${NC}"
    echo -e "${BLUE}=====================================${NC}\n"
    echo -e "1. Abre: ${YELLOW}I18N_IMPLEMENTATION_PLAN.md${NC} - Sección Día ${CURRENT_DAY}"
    echo -e "2. Usa: ${YELLOW}I18N_DAILY_CHECKLIST.md${NC} - Para tracking rápido"
    echo -e "3. Haz commits frecuentes: ${YELLOW}git commit -am \"[Day ${CURRENT_DAY}] mensaje\"${NC}"
    echo -e "4. Al terminar: ${YELLOW}./FINISH_I18N_DAY.sh${NC}\n"
    
    echo -e "${GREEN}¡Mucho éxito! 🚀${NC}\n"
else
    echo -e "${YELLOW}No hay días pendientes o ya completaste todo el plan.${NC}\n"
    echo -e "Día actual: ${CURRENT_DAY}/12\n"
fi
