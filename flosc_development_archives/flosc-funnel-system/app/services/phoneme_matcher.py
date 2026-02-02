"""
Phoneme Matching and Comparison Service
Maps transcriptions to phoneme groups and flags errors
"""
import re
from typing import List, Dict, Tuple, Set
from Levenshtein import distance as levenshtein_distance
import logging

logger = logging.getLogger(__name__)

# Phoneme group mappings (IPA to common spelling patterns)
PHONEME_PATTERNS = {
    'æ': ['cat', 'hat', 'mat', 'sat', 'flat', 'that', 'bad', 'man', 'can', 'hand'],
    'ɪ': ['it', 'sit', 'bit', 'think', 'thing', 'this', 'is', 'in', 'him'],
    'iː': ['see', 'tree', 'be', 'she', 'he', 'we', 'sea', 'beach'],
    'ɛ': ['bed', 'red', 'said', 'head', 'friend', 'get', 'let'],
    'ʌ': ['but', 'cup', 'love', 'some', 'come', 'done'],
    'ɑ': ['hot', 'not', 'got', 'father', 'calm'],
    'ɒ': ['on', 'off', 'dog', 'long'],
    'ʊ': ['put', 'look', 'book', 'good', 'could'],
    'uː': ['too', 'food', 'blue', 'you', 'move'],
    'ɔː': ['or', 'more', 'door', 'floor', 'call'],
    'ə': ['the', 'a', 'about', 'banana'],  # schwa
    'eɪ': ['day', 'say', 'make', 'take', 'late'],
    'aɪ': ['my', 'by', 'time', 'like', 'right'],
    'ɔɪ': ['boy', 'toy', 'voice', 'choice'],
    'aʊ': ['now', 'how', 'out', 'house', 'town'],
    'oʊ': ['go', 'no', 'show', 'boat', 'hope'],
    'ɪə': ['here', 'ear', 'near', 'fear'],
    'ɛə': ['there', 'where', 'care', 'hair'],
    'ʊə': ['poor', 'sure', 'tour'],
    
    # R-colored vowels
    'ɜr': ['her', 'bird', 'first', 'turn', 'work'],
    'ɑr': ['car', 'far', 'star', 'hard'],
    'ɔr': ['or', 'more', 'door', 'floor'],
    'ɪr': ['here', 'near', 'beer'],
    'ɛr': ['there', 'where', 'care'],
    'ʊr': ['poor', 'sure'],
    
    # Consonants
    'θ': ['thick', 'thin', 'think', 'thank', 'throw'],
    'ð': ['this', 'that', 'the', 'they', 'mother'],
    'ʃ': ['she', 'ship', 'fish', 'wash'],
    'ʒ': ['measure', 'vision', 'azure'],
    'tʃ': ['church', 'chair', 'teach'],
    'dʒ': ['judge', 'jump', 'bridge'],
    'ŋ': ['sing', 'thing', 'long', 'bank'],
}


def normalize_text(text: str) -> str:
    """Normalize text for comparison (lowercase, remove punctuation)"""
    text = text.lower()
    text = re.sub(r'[^\w\s]', '', text)
    return text.strip()


def calculate_similarity(expected: str, actual: str) -> float:
    """
    Calculate similarity between expected and actual text
    Uses Levenshtein distance
    
    Returns:
        Similarity score (0.0 to 1.0)
    """
    expected = normalize_text(expected)
    actual = normalize_text(actual)
    
    if not expected or not actual:
        return 0.0
    
    distance = levenshtein_distance(expected, actual)
    max_len = max(len(expected), len(actual))
    
    return 1.0 - (distance / max_len)


def extract_phoneme_groups(text: str) -> Set[str]:
    """
    Extract phoneme groups from text based on word patterns
    
    Args:
        text: Input text (sentence)
        
    Returns:
        Set of phoneme symbols found in text
    """
    text = normalize_text(text)
    words = text.split()
    
    found_phonemes = set()
    
    for phoneme, patterns in PHONEME_PATTERNS.items():
        for pattern in patterns:
            if any(pattern in word for word in words):
                found_phonemes.add(phoneme)
                break
    
    return found_phonemes


def identify_phoneme_errors(expected: str, actual: str, 
                           target_phonemes: List[str]) -> Dict[str, any]:
    """
    Compare expected vs actual transcription and identify phoneme errors
    
    Args:
        expected: Expected sentence text
        actual: Actual transcription from Whisper
        target_phonemes: List of phonemes this sentence should test
        
    Returns:
        Dict with error analysis
    """
    similarity = calculate_similarity(expected, actual)
    
    # Extract what phonemes were likely present based on words
    expected_phonemes = extract_phoneme_groups(expected)
    actual_phonemes = extract_phoneme_groups(actual)
    
    # Find missing or incorrectly pronounced phonemes
    missing_phonemes = expected_phonemes - actual_phonemes
    
    # Filter to only target phonemes
    target_set = set(target_phonemes)
    flagged_phonemes = list(missing_phonemes & target_set)
    
    return {
        'similarity': similarity,
        'expected_phonemes': list(expected_phonemes),
        'actual_phonemes': list(actual_phonemes),
        'flagged_phonemes': flagged_phonemes,
        'passed': similarity >= 0.7  # 70% threshold
    }


def analyze_quiz_results(recordings: List[Dict]) -> Dict[str, any]:
    """
    Analyze all recordings from a quiz and generate summary
    
    Args:
        recordings: List of recording analysis results
        
    Returns:
        Overall quiz analysis
    """
    all_flagged_phonemes = set()
    total_similarity = 0.0
    passed_count = 0
    
    for recording in recordings:
        if recording.get('flagged_phonemes'):
            all_flagged_phonemes.update(recording['flagged_phonemes'])
        
        total_similarity += recording.get('similarity', 0.0)
        if recording.get('passed', False):
            passed_count += 1
    
    total = len(recordings)
    avg_similarity = total_similarity / total if total > 0 else 0.0
    
    flagged_list = sorted(list(all_flagged_phonemes))
    
    return {
        'total_sentences': total,
        'passed_sentences': passed_count,
        'accuracy_score': avg_similarity,
        'flagged_phonemes': flagged_list,
        'needs_work_count': len(flagged_list),
        'message': generate_feedback_message(len(flagged_list), avg_similarity)
    }


def generate_feedback_message(error_count: int, accuracy: float) -> str:
    """Generate personalized feedback message"""
    if error_count == 0 and accuracy >= 0.9:
        return "Excellent! Your pronunciation is very accurate. 🎉"
    elif error_count <= 2 and accuracy >= 0.7:
        return "Great job! Just a few sounds to refine. 👍"
    elif error_count <= 5:
        return "Good effort! Let's work on improving these specific sounds. 📚"
    else:
        return "There's room for improvement. Our targeted lessons will help you master these sounds! 💪"


def get_lessons_for_phonemes(phonemes: List[str], lessons_data: List[Dict]) -> List[Dict]:
    """
    Get recommended lessons based on flagged phonemes
    
    Args:
        phonemes: List of phoneme symbols that need work
        lessons_data: All available lessons from CSV
        
    Returns:
        List of recommended lesson dicts (max 2 free lessons)
    """
    recommended = []
    
    for lesson in lessons_data:
        if lesson.get('phoneme_group') in phonemes and not lesson.get('is_premium'):
            recommended.append(lesson)
            if len(recommended) >= 2:
                break
    
    return recommended
