# Flujo de pantallas 📱

## Primer uso
```
Splash 💛 → Bienvenida (animada) → "Comenzar"
       → ¿Qué querés hacer?
            ├─ 🙋 Contratar servicios  → Registro (rol cliente)
            └─ 🛠️ Ofrecer servicios   → Registro (rol prestador) → Mi perfil de prestador
Registro/Login: email+contraseña (Google/Apple/teléfono listos para conectar)
```

## Cliente (tabs: Inicio · Trabajos · Chats · Avisos · Perfil)
```
Inicio → buscar o tocar categoría → Resultados (filtros: ⭐ precio 📍distancia)
      → Perfil del prestador (fotos, certificados, opiniones, tarifas)
           ├─ Contratar 🤝 (hoja: servicio, unidad, cantidad, fecha, dirección, total en vivo)
           ├─ 💬 Chatear   └─ 💛 Favorito
Trabajos → Detalle (timeline de estados)
   ├─ Pagar 💳 (tarjeta/MP/transferencia) → comprobante en Perfil→Pagos
   ├─ Extender ⏰ (agrega horas/días/…)
   ├─ Cancelar / Disputa 😟
   └─ Calificar ⭐ (al finalizar)
```

## Prestador (tabs: Trabajos · Ingresos · Chats · Mi perfil · Cuenta)
```
Trabajos → Detalle → Aceptar ✅ / Rechazar → Voy en camino 🚶 → Empezar 💪 → Terminado 🎉
Ingresos → saldo disponible / en camino / total ganado
        → Retirar 🏦 (monto + CBU/alias) → estado del retiro
Mi perfil → bio, experiencia, tarifas hora/día, servicios (chips),
            fotos 📸, certificados 🎓, horarios 🗓️, disponible 🟢/⚪
```

## Reglas UX
- Máximo **2 toques** para las acciones importantes (contratar: tocar
  prestador → Contratar; pagar: abrir trabajo → Pagar).
- Botones ≥ 58 px, tipografía base 17 px, foco visible, `aria-label` en
  iconos, `prefers-reduced-motion` respetado.
- Feedback inmediato: toasts, microanimaciones, estados vacíos con emoji,
  skeletons durante la carga.
