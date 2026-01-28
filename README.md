# 👗 Smart Closet - AI-Powered Wardrobe Management

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-stack&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-Data-4479A1?style=flat-stack&logo=mysql)
![AI](https://img.shields.io/badge/AI-ChatGPT%20%7C%20Claude%20%7C%20Gemini-orange)

### 🚀 El Concepto
**Smart Closet** es una aplicación inteligente desarrollada para optimizar la gestión de vestuario personal. A diferencia de un inventario estático, este sistema actúa como un **asistente estratégico** que combina datos meteorológicos en tiempo real, análisis de uso e inteligencia artificial para maximizar la versatilidad del armario.

---

## 🧠 Integración de IA & "Vibes Coding"
Este proyecto implementa un flujo de trabajo optimizado para **LLMs (ChatGPT, Claude, Gemini)**:
* **Prompt Engineering:** Generación automática de prompts estructurados basados en el inventario real y el clima actual.
* **Sugerencias Inteligentes:** Procesamiento de respuestas de IA para recomendar outfits según contexto (Trabajo, Universidad, Eventos).
* **Vibes Coding:** El desarrollo fue iterado utilizando asistentes de IA para acelerar la creación de lógica compleja y automatizaciones.

## ✨ Capacidades Principales
* **Gestión Avanzada de Prendas:** Clasificación por tipo, tela, color y " suitability" climática.
* **Panel de Analytics:** +10 gráficos interactivos que muestran puntuaciones de versatilidad y composición del vestuario.
* **Integración OpenWeatherMap:** Sincronización en tiempo real con el pronóstico de 5 días para sugerencias proactivas.
* **Seguimiento de Uso:** Sistema de límites semanales para fomentar la rotación de ropa y evitar el desgaste excesivo.
* **Automatización Crónica:** Scripts en segundo plano para reinicio de contadores y actualizaciones de estado diarias.

## 🛠️ Stack Tecnológico
* **Backend:** PHP & MySQL (Arquitectura relacional robusta).
* **Frontend:** HTML5, CSS3, JavaScript (Dashboard dinámico).
* **Integraciones:** API de OpenWeatherMap, External AI Models.

---

## 📂 Estructura del Proyecto (Documentación Detallada)
Para profundizar en la lógica de cada subsistema, consulta los siguientes módulos:
1. [Arquitectura & DB](./docs/architecture.md) - Esquema relacional y hub principal.
2. [Gestión de Outfits](./docs/outfits.md) - Lógica de creación y seguimiento.
3. [AI & Integration](./docs/ai.md) - Flujo de prompts y sugerencias.
4. [Backend Automation](./docs/automation.md) - Cronjobs y tareas de mantenimiento.

---

## 🔧 Instalación
1. Clonar: `git clone https://github.com/tu-usuario/smart-closet.git`
2. Configurar la API Key de OpenWeatherMap en `config.php`.
3. Ejecutar `setup.sql` para inicializar la base de datos.
