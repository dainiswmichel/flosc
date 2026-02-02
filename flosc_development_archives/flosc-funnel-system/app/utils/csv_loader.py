"""
CSV Data Loader
Loads product, lessons, and sentences from CSV files
"""
import pandas as pd
from typing import List, Dict
from app.models import Product, TargetedLesson, MagicSentence
from pathlib import Path
import logging

logger = logging.getLogger(__name__)

# Cache loaded data
_product_cache = None
_lessons_cache = None
_sentences_cache = None


def load_product() -> Product:
    """Load product configuration from CSV"""
    global _product_cache
    
    if _product_cache is None:
        try:
            df = pd.read_csv('data/product.csv')
            row = df.iloc[0]  # Get first row
            _product_cache = Product(
                product_id=int(row['product_id']),
                product_name=str(row['product_name']),
                full_price=float(row['full_price']),
                oto_price=float(row['oto_price']),
                oto_discount_percent=int(row['oto_discount_percent']),
                currency=str(row.get('currency', 'USD'))
            )
            logger.info(f"✅ Loaded product: {_product_cache.product_name}")
        except Exception as e:
            logger.error(f"❌ Error loading product.csv: {e}")
            # Fallback to defaults
            _product_cache = Product(
                product_id=1,
                product_name="Master the 44 Sounds of English",
                full_price=575.0,
                oto_price=144.0,
                oto_discount_percent=75,
                currency="USD"
            )
    
    return _product_cache


def load_lessons() -> List[TargetedLesson]:
    """Load lessons from CSV"""
    global _lessons_cache
    
    if _lessons_cache is None:
        try:
            df = pd.read_csv('data/targeted_lessons.csv')
            _lessons_cache = []
            
            for _, row in df.iterrows():
                lesson = TargetedLesson(
                    lesson_id=int(row['lesson_id']),
                    lesson_title=str(row['lesson_title']),
                    phoneme_group=str(row['phoneme_group']),
                    video_url=str(row['video_url']),
                    pdf_url=str(row.get('pdf_url', '')),
                    is_premium=bool(int(row.get('is_premium', 0)))
                )
                _lessons_cache.append(lesson)
            
            logger.info(f"✅ Loaded {len(_lessons_cache)} lessons")
        except Exception as e:
            logger.error(f"❌ Error loading targeted_lessons.csv: {e}")
            _lessons_cache = []
    
    return _lessons_cache


def load_magic_sentences() -> List[MagicSentence]:
    """Load magic sentences from CSV"""
    global _sentences_cache
    
    if _sentences_cache is None:
        try:
            df = pd.read_csv('data/magic_sentences.csv')
            _sentences_cache = []
            
            for _, row in df.iterrows():
                sentence = MagicSentence(
                    sentence_id=int(row['sentence_id']),
                    sentence_text=str(row['sentence_text']),
                    target_phonemes=str(row['target_phonemes'])  # Will be parsed by model
                )
                _sentences_cache.append(sentence)
            
            logger.info(f"✅ Loaded {len(_sentences_cache)} magic sentences")
        except Exception as e:
            logger.error(f"❌ Error loading magic_sentences.csv: {e}")
            # Fallback sentences
            _sentences_cache = [
                MagicSentence(
                    sentence_id=1,
                    sentence_text="The cat sat on the flat mat",
                    target_phonemes="æ|ɪ|ɒ"
                ),
                MagicSentence(
                    sentence_id=2,
                    sentence_text="I think this thing is thick",
                    target_phonemes="ɪ|θ|ŋ"
                ),
                MagicSentence(
                    sentence_id=3,
                    sentence_text="She sells seashells by the seashore",
                    target_phonemes="ʃ|s|iː"
                )
            ]
    
    return _sentences_cache


def reload_data():
    """Force reload all CSV data (for when creator updates files)"""
    global _product_cache, _lessons_cache, _sentences_cache
    _product_cache = None
    _lessons_cache = None
    _sentences_cache = None
    
    # Reload
    load_product()
    load_lessons()
    load_magic_sentences()
    
    logger.info("✅ All CSV data reloaded")


def get_lesson_by_phoneme(phoneme: str) -> List[Dict]:
    """Get lessons that target a specific phoneme"""
    lessons = load_lessons()
    return [
        lesson.dict() for lesson in lessons 
        if lesson.phoneme_group == phoneme
    ]


def get_free_lessons() -> List[Dict]:
    """Get all free (non-premium) lessons"""
    lessons = load_lessons()
    return [
        lesson.dict() for lesson in lessons 
        if not lesson.is_premium
    ]
