"""
FIRESIGHT Fire Verification Service
-----------------------------------------------
Loads fire_verification_model.onnx and exposes a /predict endpoint that
your PHP backend calls after a resident uploads a report photo.

Run locally (VSCode):
    pip install -r requirements.txt
    uvicorn main:app --reload --port 8000

The PHP backend calls: POST http://localhost:8000/predict
    (multipart/form-data, field name: "image")

Response:
    {
      "fire": true,
      "confidence": 0.97,
      "label": "fire"
    }
"""

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import JSONResponse
import onnxruntime as ort
import numpy as np
from PIL import Image
import io
import os

app = FastAPI(title="FIRESIGHT Fire Verification Service")

# ---- Config ----
MODEL_PATH = os.path.join(os.path.dirname(__file__), "model", "fire_verification_model.onnx")
IMG_SIZE = (224, 224)  # must match training (MobileNetV3, 224x224)
CONFIDENCE_THRESHOLD = 0.5  # >= this => classified as fire (label 1 = fire, per training)

# ---- Load model once at startup ----
ort_session = None

@app.on_event("startup")
def load_model():
    global ort_session
    if not os.path.exists(MODEL_PATH):
        raise RuntimeError(f"Model file not found at {MODEL_PATH}")
    ort_session = ort.InferenceSession(MODEL_PATH, providers=["CPUExecutionProvider"])
    print(f"Model loaded from {MODEL_PATH}")


def preprocess_image(image_bytes: bytes) -> np.ndarray:
    """Resize + apply MobileNetV3 preprocessing (scale to [-1, 1]),
    matching keras.applications.mobilenet_v3.preprocess_input exactly."""
    image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
    image = image.resize(IMG_SIZE)
    arr = np.array(image).astype(np.float32)

    # MobileNetV3's preprocess_input maps pixel values from [0, 255] to [-1, 1]
    arr = (arr / 127.5) - 1.0

    arr = np.expand_dims(arr, axis=0)  # shape: (1, 224, 224, 3)
    return arr


@app.get("/health")
def health_check():
    return {"status": "ok", "model_loaded": ort_session is not None}


@app.post("/predict")
async def predict(image: UploadFile = File(...)):
    if ort_session is None:
        raise HTTPException(status_code=503, detail="Model not loaded yet")

    if not image.content_type or not image.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Uploaded file must be an image")

    try:
        image_bytes = await image.read()
        input_array = preprocess_image(image_bytes)
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Could not process image: {e}")

    input_name = ort_session.get_inputs()[0].name
    output = ort_session.run(None, {input_name: input_array})[0]
    confidence = float(output.flatten()[0])  # label 1 = fire (per training label flip)

    is_fire = confidence >= CONFIDENCE_THRESHOLD

    return JSONResponse({
        "fire": is_fire,
        "confidence": round(confidence, 4),
        "label": "fire" if is_fire else "non_fire",
    })