#!/bin/bash
# Script para ver el estado actual de la implementación i18n

# Colores
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

clear
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         i18n Implementation - Estado del Proyecto         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}\n"

# Leer variables del archivo de progreso
CURRENT_DAY=$(grep "^CURRENT_DAY=" .i18n-progress | cut -d'=' -f2)
TOTAL_DAYS=12
START_DATE=$(grep "^START_DATE=" .i18n-progress | cut -d'=' -f2)
CURRENT_STATUS=$(grep "^CURRENT_STATUS=" .i18n-progress | cut -d'=' -f2)

# Calcular porcentaje
if [ "$CURRENT_DAY" -gt 0 ]; then
    PERCENTAGE=$(( (CURRENT_DAY - 1) * 100 / TOTAL_DAYS ))
else
    PERCENTAGE=0
fi

# Status general
echo -e "${YELLOW}📊 Progreso General${NC}"
echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "Día actual:      ${GREEN}${CURRENT_DAY}${NC} / ${TOTAL_DAYS}"
echo -e "Progreso:        ${GREEN}${PERCENTAGE}%${NC}"
echo -e "Estado:          ${YELLOW}${CURRENT_STATUS}${NC}"
if [ -n "$START_DATE" ]; then
    echo -e "Fecha inicio:    ${BLUE}${START_DATE}${NC}"
fi
echo -e ""

# Detalle por día
echo -e "${YELLOW}📅 Detalle por Día${NC}"
echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

for i in {1..12}; do
    DAY_STATUS=$(grep "^DAY_${i}_STATUS=" .i18n-progress | cut -d'=' -f2)
    DAY_DATE=$(grep "^DAY_${i}_DATE=" .i18n-progress | cut -d'=' -f2)
    DAY_HOURS=$(grep "^DAY_${i}_HOURS=" .i18n-progress | cut -d'=' -f2)
    DAY_NOTES=$(grep "^DAY_${i}_NOTES=" .i18n-progress | cut -d'=' -f2 | tr -d '"')
    
    # Emoji por estado
    case "$DAY_STATUS" in
        "completed")
            EMOJI="✅"
            COLOR=$GREEN
            ;;
        "in_progress")
            EMOJI="🔄"
            COLOR=$YELLOW
            ;;
        "blocked")
            EMOJI="⚠️"
            COLOR=$RED
            ;;
        *)
            EMOJI="⏸️"
            COLOR=$NC
            ;;
    esac
    
    # Descripción del día
    case $i in
        1) DESC="Fundamentos y Configuración" ;;
        2) DESC="Base de Datos y Modelos" ;;
        3) DESC="Archivos de Traducción" ;;
        4) DESC="Controladores - Search & Detail" ;;
        5) DESC="Controladores - Dashboard CRUD" ;;
        6) DESC="Vistas Blade - Páginas Públicas" ;;
        7) DESC="Vistas Blade - Dashboard" ;;
        8) DESC="Embeddings y Búsqueda IA" ;;
        9) DESC="SEO y Sitemap" ;;
        10) DESC="Emails y Notificaciones" ;;
        11) DESC="Filament Admin Panel" ;;
        12) DESC="Testing y Optimización" ;;
    esac
    
    printf "${COLOR}${EMOJI} Día %2d: %-35s${NC}" $i "$DESC"
    
    if [ "$DAY_STATUS" != "not_started" ]; then
        printf " [${DAY_HOURS}h]"
        if [ -n "$DAY_DATE" ]; then
            printf " (${DAY_DATE})"
        fi
    fi
    printf "\n"
    
    if [ -n "$DAY_NOTES" ] && [ "$DAY_NOTES" != "" ]; then
        echo -e "         ${BLUE}└─ ${DAY_NOTES}${NC}"
    fi
done

echo -e ""

# Estadísticas
COMPLETED=$(grep "STATUS=completed" .i18n-progress | wc -l)
IN_PROGRESS=$(grep "STATUS=in_progress" .i18n-progress | wc -l)
BLOCKED=$(grep "STATUS=blocked" .i18n-progress | wc -l)
TOTAL_HOURS=$(grep "^DAY_.*_HOURS=" .i18n-progress | cut -d'=' -f2 | awk '{s+=$1} END {print s}')

echo -e "${YELLOW}📈 Estadísticas${NC}"
echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "Días completados:    ${GREEN}${COMPLETED}${NC} / ${TOTAL_DAYS}"
echo -e "Días en progreso:    ${YELLOW}${IN_PROGRESS}${NC}"
echo -e "Días bloqueados:     ${RED}${BLOCKED}${NC}"
echo -e "Horas trabajadas:    ${BLUE}${TOTAL_HOURS}h${NC}"
echo -e ""

# Próximos pasos
if [ "$CURRENT_DAY" -gt 0 ] && [ "$CURRENT_DAY" -le 12 ]; then
    echo -e "${YELLOW}🎯 Próximos Pasos${NC}"
    echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if [ "$CURRENT_STATUS" = "not_started" ]; then
        echo -e "1. Ejecuta: ${GREEN}./START_I18N.sh${NC} para empezar el Día 1"
    else
        if [ "$IN_PROGRESS" -gt 0 ]; then
            echo -e "1. Continúa trabajando en el Día ${CURRENT_DAY}"
            echo -e "2. Consulta: ${BLUE}I18N_DAILY_CHECKLIST.md${NC}"
            echo -e "3. Al terminar: ${GREEN}./FINISH_I18N_DAY.sh${NC}"
        else
            NEXT_DAY=$((CURRENT_DAY + 1))
            if [ "$NEXT_DAY" -le 12 ]; then
                echo -e "1. Ejecuta: ${GREEN}./START_I18N.sh${NC} para empezar el Día ${NEXT_DAY}"
            else
                echo -e "${GREEN}✅ ¡Implementación i18n completada! 🎉${NC}"
            fi
        fi
    fi
    echo -e ""
fi

# Barra de progreso visual
echo -e "${YELLOW}Progreso Visual${NC}"
echo -e "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
FILLED=$(( PERCENTAGE / 2 ))
EMPTY=$(( 50 - FILLED ))
printf "["
printf "${GREEN}%${FILLED}s${NC}" | tr ' ' '█'
printf "%${EMPTY}s" | tr ' ' '░'
printf "] ${GREEN}${PERCENTAGE}%%${NC}\n"
echo -e ""
